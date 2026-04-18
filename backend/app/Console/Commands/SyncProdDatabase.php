<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SyncProdDatabase extends Command
{
    protected $signature = 'db:sync-prod
        {--force : 略過互動確認（排程使用）}
        {--host=139.162.121.187 : 正式站 SSH host}
        {--user=root : 正式站 SSH user}
        {--key= : SSH 私鑰路徑，預設 ~/.ssh/pandora_deploy_key}
        {--remote-path=/var/www/pandora/backend : 正式站 Laravel 專案路徑}
        {--no-migrate : 匯入後不跑 php artisan migrate}';

    protected $description = '從正式站 pull 資料庫覆蓋本地 DB（只允許在 macOS 本機執行）';

    public function handle(): int
    {
        // ── Safety 1: OS — 拒絕在 Linux 正式機執行（避免自己把自己覆蓋）──
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->error('此指令僅允許在 macOS (Darwin) 執行。當前系統: ' . PHP_OS_FAMILY);
            return self::FAILURE;
        }

        // ── Safety 2: 本地 DB 必須指向 localhost ──
        $localDb = config('database.connections.mysql.database');
        $localHost = config('database.connections.mysql.host');
        $localPort = config('database.connections.mysql.port', 3306);
        $localUser = config('database.connections.mysql.username');
        $localPass = config('database.connections.mysql.password');

        if (! in_array($localHost, ['127.0.0.1', 'localhost', '::1'])) {
            $this->error("本地 DB host 不是 localhost（{$localHost}），拒絕執行以防誤刪遠端資料。");
            return self::FAILURE;
        }

        $sshKey = $this->option('key') ?: ($_SERVER['HOME'] . '/.ssh/pandora_deploy_key');
        if (! is_file($sshKey)) {
            $this->error("SSH 私鑰不存在：{$sshKey}");
            return self::FAILURE;
        }

        $sshTarget = $this->option('user') . '@' . $this->option('host');
        $remotePath = rtrim($this->option('remote-path'), '/');

        // ── Safety 3: 確認 ──
        $this->warn("即將從 {$sshTarget}:{$remotePath} 拉取 DB，覆蓋本地 `{$localDb}`。");
        $this->warn('本地資料庫現有內容將完全清除。');
        if (! $this->option('force') && ! $this->confirm('繼續？')) {
            $this->info('已取消。');
            return self::FAILURE;
        }

        $startedAt = microtime(true);

        // ── Step 1: 從正式站遠端 dump + gzip，串流回本地解壓匯入 ──
        // 流程（單一 shell pipeline）：
        //   ssh prod "cd /var/www/pandora/backend && export $(grep DB_ .env) \
        //             && mysqldump ... | gzip"
        //   | gunzip
        //   | mysql 本地（事先 DROP+CREATE）
        //
        // 密碼不放 argv；MariaDB/MySQL 支援 MYSQL_PWD 環境變數。
        $this->line('▶ 正在從正式站 dump DB 並串流回本地 …');

        // 先 drop + create 本地 DB 確保乾淨
        $dropCreateOk = $this->runLocalMysql(
            $localHost,
            (int) $localPort,
            $localUser,
            $localPass,
            null,
            "DROP DATABASE IF EXISTS `{$localDb}`; CREATE DATABASE `{$localDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        );
        if (! $dropCreateOk) {
            $this->error('重建本地 DB 失敗。');
            return self::FAILURE;
        }
        $this->line("  · 已 DROP + CREATE 本地 DB `{$localDb}`");

        // 遠端指令：source .env 取 DB_* 變數，mysqldump 後 gzip 到 stdout。
        // 密碼透過 MYSQL_PWD 環境變數傳，不進 argv（避免出現在 ps）。
        $remoteCmd = <<<BASH
            set -e
            cd {$remotePath}
            set -a
            . ./.env
            set +a
            : "\${DB_HOST:=127.0.0.1}"
            : "\${DB_PORT:=3306}"
            export MYSQL_PWD="\$DB_PASSWORD"
            mysqldump \
                --single-transaction --quick --routines --triggers --events \
                --no-tablespaces \
                --default-character-set=utf8mb4 \
                --add-drop-table \
                -h "\$DB_HOST" -P "\$DB_PORT" -u "\$DB_USERNAME" "\$DB_DATABASE" \
            | gzip -c
        BASH;

        $sshCmd = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 -o ServerAliveCountMax=120 %s %s',
            escapeshellarg($sshKey),
            escapeshellarg($sshTarget),
            escapeshellarg($remoteCmd),
        );

        // 本地解壓 + 匯入。mysql 的密碼用 MYSQL_PWD 環境變數傳，不進 argv。
        $mysqlImportCmd = sprintf(
            'gunzip -c | mysql --default-character-set=utf8mb4 -h %s -P %d -u %s %s',
            escapeshellarg($localHost),
            (int) $localPort,
            escapeshellarg($localUser),
            escapeshellarg($localDb),
        );

        $pipeline = $sshCmd . ' | ' . $mysqlImportCmd;

        $process = Process::fromShellCommandline($pipeline);
        $process->setTimeout(60 * 30); // 30 min 上限（Linode 1C DB 不大）
        $process->setEnv(['MYSQL_PWD' => (string) $localPass]);

        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                // mysqldump/ssh 的警告與進度都寫 stderr — 逐行前綴輸出
                foreach (explode("\n", rtrim($buffer)) as $line) {
                    if ($line !== '') {
                        $this->line('  <fg=gray>' . $line . '</>');
                    }
                }
            }
        });

        if (! $process->isSuccessful()) {
            $this->error('同步失敗，exit code ' . $process->getExitCode());
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $startedAt, 1);
        $this->line("  · dump + import 完成（{$elapsed}s）");

        // ── Step 2: 跑 migrate 補上 local 未套用的 migration（可選）──
        if (! $this->option('no-migrate')) {
            $this->line('▶ 跑 php artisan migrate --force …');
            $code = $this->call('migrate', ['--force' => true]);
            if ($code !== 0) {
                $this->warn('migrate 回傳非 0，可能 prod schema 已含該 migration — 通常無害。');
            }
        }

        // ── Step 3: 清快取 ──
        $this->call('cache:clear');
        $this->call('config:clear');

        $totalElapsed = round(microtime(true) - $startedAt, 1);
        $this->info("✓ 正式站 DB 已同步到本地 `{$localDb}`（總耗時 {$totalElapsed}s）");

        return self::SUCCESS;
    }

    /**
     * 跑 mysql 指令（密碼透過 MYSQL_PWD env 傳，不進 argv）。
     */
    protected function runLocalMysql(string $host, int $port, string $user, ?string $password, ?string $database, string $sql): bool
    {
        $cmd = ['mysql', '-h', $host, '-P', (string) $port, '-u', $user];
        if ($database !== null) {
            $cmd[] = $database;
        }
        $cmd[] = '-e';
        $cmd[] = $sql;

        $process = new Process($cmd);
        $process->setEnv(['MYSQL_PWD' => (string) ($password ?? '')]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()));
            return false;
        }
        return true;
    }
}

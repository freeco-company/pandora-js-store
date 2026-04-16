<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Pre-flight checklist for production — run after any deploy, before go-live.
 *   php artisan prod:verify
 * Exits non-zero if any critical check fails so CI/deploy scripts can gate.
 */
class ProdVerify extends Command
{
    protected $signature = 'prod:verify {--allow-dev : treat APP_ENV=local as OK}';
    protected $description = 'Verify production readiness: env, DB, mail, schedule, storage, cache';

    private array $results = [];

    public function handle(): int
    {
        $this->info('🔍 Production readiness check');
        $this->newLine();

        $this->check('APP_KEY set', fn () => ! empty(config('app.key')));
        $this->check('APP_ENV=production', fn () => config('app.env') === 'production', $this->option('allow-dev') ? 'warn' : 'fail');
        $this->check('APP_DEBUG=false', fn () => ! config('app.debug'));
        $this->check('APP_URL set to https', fn () => str_starts_with(config('app.url') ?? '', 'https://'));

        $this->check('DB connection', function () {
            DB::connection()->getPdo();
            return DB::connection()->getDatabaseName() !== null;
        });

        $this->check('ECPay merchant_id set', fn () => ! empty(config('services.ecpay.merchant_id')));
        $this->check('ECPay hash_key set',     fn () => ! empty(config('services.ecpay.hash_key')));
        $this->check('ECPay hash_iv set',      fn () => ! empty(config('services.ecpay.hash_iv')));
        $this->check('ECPay mode=production',  fn () => config('services.ecpay.mode') === 'production', 'warn');

        $this->check('Google OAuth client_id set',     fn () => ! empty(config('services.google.client_id')));
        $this->check('Google OAuth client_secret set', fn () => ! empty(config('services.google.client_secret')));

        $this->check('Mail MAILER=smtp',     fn () => config('mail.default') === 'smtp', 'warn');
        $this->check('Mail USERNAME set',    fn () => ! empty(config('mail.mailers.smtp.username')));
        $this->check('Mail PASSWORD set',    fn () => ! empty(config('mail.mailers.smtp.password')));
        $this->check('Mail FROM address',    fn () => ! empty(config('mail.from.address')));

        $this->check('Discord compliance webhook', fn () => ! empty(config('services.discord.compliance_webhook')), 'warn');

        $this->check('Cache writable',            function () {
            Cache::put('prod-verify', '1', 10);
            return Cache::get('prod-verify') === '1';
        });

        $this->check('storage/app/public symlink', fn () => is_link(public_path('storage')), 'warn');

        $this->check('Scheduler entries registered', function () {
            $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->count();
            return $scheduled > 0;
        }, 'warn');

        $failed = collect($this->results)->where('status', 'FAIL')->count();
        $warned = collect($this->results)->where('status', 'WARN')->count();

        $this->newLine();
        $this->table(['Status', 'Check', 'Note'], $this->results);
        $this->newLine();

        if ($failed > 0) {
            $this->error("❌ {$failed} critical check(s) failed.");
            return self::FAILURE;
        }
        if ($warned > 0) {
            $this->warn("⚠️  {$warned} warning(s) — review before launch.");
        } else {
            $this->info('✅ All production checks passed.');
        }
        return self::SUCCESS;
    }

    /**
     * @param  'fail'|'warn'  $severity
     */
    private function check(string $label, \Closure $fn, string $severity = 'fail'): void
    {
        try {
            $ok = (bool) $fn();
        } catch (\Throwable $e) {
            $ok = false;
            $note = substr($e->getMessage(), 0, 60);
        }
        $this->results[] = [
            'status' => $ok ? 'OK' : strtoupper($severity),
            'check'  => $label,
            'note'   => $note ?? ($ok ? '' : ($severity === 'warn' ? 'non-critical' : 'must-fix')),
        ];
    }
}

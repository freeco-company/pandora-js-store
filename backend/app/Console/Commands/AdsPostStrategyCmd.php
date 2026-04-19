<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;

/**
 * Posts a strategy analysis (typically from Claude Code) to the Ads
 * Discord channel. Accepts markdown body via stdin, optional title + color.
 *
 * Designed to be piped from SSH — keeps the webhook URL server-side,
 * so the analyst client never needs to know or store it.
 *
 * Usage:
 *   echo "markdown analysis" | ssh prod 'php artisan ads:post-strategy'
 *   cat analysis.md | ssh prod 'php artisan ads:post-strategy --title="週報"'
 */
class AdsPostStrategyCmd extends Command
{
    protected $signature = 'ads:post-strategy
        {--title=🧠 今日策略建議 : Embed title}
        {--color=10447166 : Decimal embed color (default: #9F6B3E brand)}';

    protected $description = 'Post a strategy analysis (markdown via stdin) to the Ads Discord channel';

    public function handle(): int
    {
        $body = trim(file_get_contents('php://stdin') ?: '');
        if ($body === '') {
            $this->error('No input on stdin. Pipe markdown text into this command.');
            return self::FAILURE;
        }

        // Discord embed description limit is 4000. Trim gracefully.
        $body = mb_substr($body, 0, 4000);

        $notifier = DiscordNotifier::adsStrategy();
        if (! $notifier->isEnabled()) {
            $this->error('DISCORD_ADS_STRATEGY_WEBHOOK (and fallbacks) not configured.');
            return self::FAILURE;
        }

        $ok = $notifier->embed(
            title: (string) $this->option('title'),
            description: $body,
            fields: [],
            color: (int) $this->option('color'),
        );

        if ($ok) {
            $this->info('Posted analysis to Discord.');
            return self::SUCCESS;
        }

        $this->error('Failed to post (check logs).');
        return self::FAILURE;
    }
}

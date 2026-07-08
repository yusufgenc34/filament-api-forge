<?php

namespace YusufGenc34\FilamentApiForge\Commands;

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Notifications\ApiForgeTokenExpiringNotification;
use Illuminate\Console\Command;

class NotifyExpiringTokensCommand extends Command
{
    protected $signature = 'api-forge:notify-expiring
                            {--days= : Notify for tokens expiring within this many days}';

    protected $description = 'Notify owners of API tokens that are about to expire';

    public function handle(): int
    {
        $days = (int) ($this->option('days')
            ?? config('filament-api-forge.notifications.expiry_days', 7));

        $tokens = ApiForgeToken::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->whereNull('expiry_notified_at')
            ->with('user')
            ->get();

        $notified = 0;
        $failed   = 0;

        foreach ($tokens as $token) {
            $user = $token->user;

            if (! $user || ! method_exists($user, 'notify')) {
                continue;
            }

            try {
                $user->notify(new ApiForgeTokenExpiringNotification($token));

                $token->forceFill(['expiry_notified_at' => now()])->save();
                $notified++;
            } catch (\Throwable $e) {
                // A failing channel must not abort the run or re-send
                // already-delivered notifications on the next attempt.
                $failed++;
                $this->warn("Failed to notify owner of token '{$token->name}': {$e->getMessage()}");
            }
        }

        $this->info("Notified {$notified} token owner(s) about upcoming expiry (window: {$days} days)."
            . ($failed > 0 ? " {$failed} failed." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

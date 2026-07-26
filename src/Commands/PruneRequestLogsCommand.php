<?php

namespace YusufGenc34\FilamentApiForge\Commands;

use Illuminate\Console\Command;
use YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog;

class PruneRequestLogsCommand extends Command
{
    protected $signature = 'api-forge:prune-logs
                            {--days= : Delete logs older than this many days}';

    protected $description = 'Prune old API request logs';

    public function handle(): int
    {
        $days = (int) ($this->option('days')
            ?? config('filament-api-forge.audit.prune_days', 30));

        $deleted = ApiForgeRequestLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} request log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}

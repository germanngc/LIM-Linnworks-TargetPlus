<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;

class PruneSyncLogs extends Command
{
    protected $signature = 'sync:prune-logs {--days=90 : Retention period in days}';
    protected $description = 'Delete synchronization log entries older than the retention period.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = SyncLog::where('logged_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} sync log entries older than {$days} days.");
        return Command::SUCCESS;
    }
}

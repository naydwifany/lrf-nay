<?php
// app/Console/Commands/CleanActivityLogs.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ActivityLogService;

class CleanActivityLogs extends Command
{
    protected $signature = 'activitylog:clean {--days=365 : Number of days to keep}';
    protected $description = 'Clean old activity logs';

    public function handle()
    {
        $days = $this->option('days');
        
        $this->info("Cleaning activity logs older than {$days} days...");
        
        try {
            $service = new ActivityLogService();
            $deletedCount = $service->cleanOldLogs($days);
            
            $this->info("Successfully deleted {$deletedCount} old activity log records.");
            return 0;
        } catch (\Exception $e) {
            $this->error('Error cleaning activity logs: ' . $e->getMessage());
            \Log::error('Activity log cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }
}
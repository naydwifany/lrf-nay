<?php
// app/Console/Commands/ProcessDocumentNotifications.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentNotificationService;

class ProcessDocumentNotifications extends Command
{
    protected $signature = 'documents:process-notifications {--dry-run : Show what notifications would be sent without actually sending them}';
    protected $description = 'Process document notifications and reminders';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No notifications will actually be sent');
        }
        
        $this->info('Processing document notifications...');
        
        try {
            $service = new DocumentNotificationService();
            $service->processDocumentReminders();
            
            $this->info('Document notifications processed successfully.');
            
        } catch (\Exception $e) {
            $this->error('Error processing notifications: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
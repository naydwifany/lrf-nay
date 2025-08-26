<?php
// app/Console/Commands/TestNotifications.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\MasterDocument;

class TestNotifications extends Command
{
    protected $signature = 'documents:test-notifications';
    protected $description = 'Test notification system with sample data';

    public function handle()
    {
        $this->info('Testing notification system...');
        
        // Check if we have data
        $documentCount = DocumentRequest::count();
        $typeCount = MasterDocument::count();
        
        $this->info("Found {$documentCount} document requests");
        $this->info("Found {$typeCount} document types");
        
        if ($typeCount == 0) {
            $this->warn('No document types found. Creating sample...');
            $this->createSampleDocumentType();
        }
        
        if ($documentCount == 0) {
            $this->warn('No document requests found. You may want to create some test data.');
        }
        
        // Test notification service
        try {
            $service = new \App\Services\DocumentNotificationService();
            $service->processDocumentReminders();
            $this->info('Notification service ran successfully!');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
        
        return 0;
    }
    
    private function createSampleDocumentType()
    {
        MasterDocument::create([
            'document_name' => 'Service Agreement',
            'document_code' => 'SA',
            'description' => 'Standard service agreement template',
            'is_active' => true,
            'enable_notifications' => true,
            'warning_days' => 7,
            'urgent_days' => 3,
            'critical_days' => 1,
            'notification_recipients' => [
                'default_recipients' => ['requester', 'supervisor'],
                'custom_emails' => []
            ],
            'notification_message_template' => 'Document "{document_title}" requires attention. {days_remaining} days remaining.'
        ]);
        
        $this->info('Sample document type created.');
    }
}
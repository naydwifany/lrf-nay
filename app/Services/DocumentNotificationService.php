<?php
// app/Services/DocumentNotificationService.php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\MasterDocument;
use App\Models\Notification;
use App\Models\AgreementOverview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DocumentNotificationService
{
    public function processDocumentReminders()
    {
        Log::info('Starting document notification processing...');
        
        $documentTypes = MasterDocument::where('enable_notifications', true)->get();
        
        foreach ($documentTypes as $documentType) {
            $this->processNotificationsForDocumentType($documentType);
        }
        
        Log::info('Document notification processing completed.');
    }

    protected function processNotificationsForDocumentType(MasterDocument $documentType)
    {
        // Check Document Requests yang masih pending dan ada target completion
        $documents = DocumentRequest::where('tipe_dokumen', $documentType->id)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->where('is_draft', false) // Only submitted documents
            ->whereNotNull('submitted_at')
            ->get();

        foreach ($documents as $document) {
            $this->checkDocumentRequestNotifications($document, $documentType);
        }

        // Check Agreement Overviews yang mendekati expiry
        $agreements = AgreementOverview::whereHas('lrfDocument', function($query) use ($documentType) {
                $query->where('tipe_dokumen', $documentType->id);
            })
            ->whereNotNull('end_date_jk')
            ->where('status', '!=', 'rejected')
            ->get();

        foreach ($agreements as $agreement) {
            $this->checkAgreementExpiryNotifications($agreement, $documentType);
        }
    }

    protected function checkDocumentRequestNotifications(DocumentRequest $document, MasterDocument $documentType)
    {
        // Calculate days since submission for processing reminders
        $daysSinceSubmission = $document->submitted_at ? Carbon::now()->diffInDays($document->submitted_at) : 0;
        
        // Check for processing delays (no activity reminders)
        $this->checkProcessingDelayNotifications($document, $documentType, $daysSinceSubmission);
        
        // Check for status-based notifications
        $this->checkStatusBasedNotifications($document, $documentType);
    }

    protected function checkAgreementExpiryNotifications(AgreementOverview $agreement, MasterDocument $documentType)
    {
        if (!$agreement->end_date_jk) return;

        $daysUntilExpiry = Carbon::now()->diffInDays($agreement->end_date_jk, false);
        $notificationLevel = $documentType->getNotificationLevel($daysUntilExpiry);

        if ($notificationLevel && !$this->hasRecentNotification($agreement, $notificationLevel, 'agreement_expiry')) {
            $this->sendAgreementExpiryNotification($agreement, $documentType, $notificationLevel, $daysUntilExpiry);
        }
    }

    protected function checkProcessingDelayNotifications(DocumentRequest $document, MasterDocument $documentType, $daysSinceSubmission)
    {
        // Default processing time expectations
        $expectedProcessingDays = [
            'pending_supervisor' => 3,
            'pending_gm' => 5, 
            'pending_legal' => 7,
            'discussion' => 10,
            'agreement_creation' => 14,
        ];

        $expectedDays = $expectedProcessingDays[$document->status] ?? 7;
        
        if ($daysSinceSubmission > $expectedDays) {
            $delayDays = $daysSinceSubmission - $expectedDays;
            $this->sendProcessingDelayNotification($document, $documentType, $delayDays);
        }
    }

    protected function checkStatusBasedNotifications(DocumentRequest $document, MasterDocument $documentType)
    {
        // Send notifications based on current status and how long it's been in that status
        $lastUpdate = $document->updated_at ?: $document->submitted_at;
        $daysInCurrentStatus = $lastUpdate ? Carbon::now()->diffInDays($lastUpdate) : 0;

        // If stuck in current status for too long
        $maxDaysInStatus = [
            'pending_supervisor' => 3,
            'pending_gm' => 3,
            'pending_legal' => 5,
            'discussion' => 7,
            'agreement_creation' => 10,
        ];

        $maxDays = $maxDaysInStatus[$document->status] ?? 5;
        
        if ($daysInCurrentStatus >= $maxDays && !$this->hasRecentNotification($document, 'status_delay', 'status_reminder')) {
            $this->sendStatusReminderNotification($document, $documentType, $daysInCurrentStatus);
        }
    }

    protected function sendAgreementExpiryNotification(AgreementOverview $agreement, MasterDocument $documentType, $level, $daysUntilExpiry)
    {
        $recipients = $this->getNotificationRecipients($agreement->lrfDocument, $documentType);
        
        $message = "Agreement '{$agreement->nomor_dokumen}' will expire in {$daysUntilExpiry} days on {$agreement->end_date_jk->format('d M Y')}. Please review for renewal or extension.";

        foreach ($recipients as $recipient) {
            $this->createNotification([
                'type' => 'agreement_expiry_reminder',
                'recipient_nik' => $recipient['nik'] ?? null,
                'title' => ucfirst($level) . ' Alert: Agreement Expiring Soon',
                'message' => $message,
                'agreement_overview_id' => $agreement->id,
                'document_request_id' => $agreement->lrf_doc_id,
                'data' => [
                    'level' => $level,
                    'days_until_expiry' => $daysUntilExpiry,
                    'agreement_number' => $agreement->nomor_dokumen,
                    'notification_type' => 'agreement_expiry'
                ]
            ]);
        }
    }

    protected function sendProcessingDelayNotification(DocumentRequest $document, MasterDocument $documentType, $delayDays)
    {
        if ($this->hasRecentNotification($document, 'processing_delay', 'processing_reminder')) {
            return;
        }

        $recipients = $this->getNotificationRecipients($document, $documentType);
        
        $message = "Document '{$document->title}' has been in processing for {$delayDays} days longer than expected. Current status: {$document->status}. Please check for any required actions.";

        foreach ($recipients as $recipient) {
            $this->createNotification([
                'type' => 'processing_delay_reminder',
                'recipient_nik' => $recipient['nik'] ?? null,
                'title' => 'Processing Delay Alert',
                'message' => $message,
                'document_request_id' => $document->id,
                'data' => [
                    'delay_days' => $delayDays,
                    'current_status' => $document->status,
                    'notification_type' => 'processing_delay'
                ]
            ]);
        }
    }

    protected function sendStatusReminderNotification(DocumentRequest $document, MasterDocument $documentType, $daysInStatus)
    {
        $recipients = $this->getNotificationRecipients($document, $documentType);
        
        $actionRequired = $this->getActionRequiredMessage($document->status);
        $message = "Document '{$document->title}' has been in '{$document->status}' status for {$daysInStatus} days. {$actionRequired}";

        foreach ($recipients as $recipient) {
            $this->createNotification([
                'type' => 'status_reminder',
                'recipient_nik' => $recipient['nik'] ?? null,
                'title' => 'Document Status Reminder',
                'message' => $message,
                'document_request_id' => $document->id,
                'data' => [
                    'days_in_status' => $daysInStatus,
                    'current_status' => $document->status,
                    'notification_type' => 'status_reminder'
                ]
            ]);
        }
    }

    protected function getActionRequiredMessage($status)
    {
        return match($status) {
            'pending_supervisor' => 'Supervisor approval is required.',
            'pending_gm' => 'General Manager approval is required.',
            'pending_legal' => 'Legal team review is required.',
            'discussion' => 'Please participate in the discussion forum.',
            'agreement_creation' => 'Agreement overview creation is needed.',
            default => 'Please check if any action is required.'
        };
    }

    protected function getNotificationRecipients(DocumentRequest $document, MasterDocument $documentType)
    {
        $recipients = [];
        $settings = $documentType->notification_recipients ?? [
            'default_recipients' => ['requester', 'supervisor']
        ];

        $defaultRecipients = $settings['default_recipients'] ?? ['requester'];

        // Requester
        if (in_array('requester', $defaultRecipients)) {
            $recipients[] = [
                'nik' => $document->nik,
                'email' => $document->user->email ?? null,
                'name' => $document->nama,
            ];
        }

        // Supervisor
        if (in_array('supervisor', $defaultRecipients) && $document->supervisor) {
            $recipients[] = [
                'nik' => $document->supervisor->nik,
                'email' => $document->supervisor->email,
                'name' => $document->supervisor->name,
            ];
        }

        // Current approver based on status
        $currentApprover = $this->getCurrentApprover($document);
        if ($currentApprover && !collect($recipients)->contains('nik', $currentApprover->nik)) {
            $recipients[] = [
                'nik' => $currentApprover->nik,
                'email' => $currentApprover->email,
                'name' => $currentApprover->name,
            ];
        }

        // Legal team
        if (in_array('legal_team', $defaultRecipients)) {
            $legalUsers = \App\Models\User::where('role', 'admin_legal')
                ->orWhere('role', 'head_legal')
                ->get();
            
            foreach ($legalUsers as $legal) {
                if (!collect($recipients)->contains('nik', $legal->nik)) {
                    $recipients[] = [
                        'nik' => $legal->nik,
                        'email' => $legal->email,
                        'name' => $legal->name,
                    ];
                }
            }
        }

        // Custom emails
        if (!empty($settings['custom_emails'])) {
            foreach ($settings['custom_emails'] as $email) {
                $recipients[] = [
                    'nik' => null,
                    'email' => $email,
                    'name' => 'Team Member',
                ];
            }
        }

        return $recipients;
    }

    protected function getCurrentApprover(DocumentRequest $document)
    {
        return match($document->status) {
            'pending_supervisor' => $document->supervisor,
            'pending_gm' => \App\Models\User::where('role', 'general_manager')->first(),
            'pending_legal' => \App\Models\User::where('role', 'admin_legal')->first(),
            default => null
        };
    }

    protected function createNotification(array $data)
    {
        try {
            // Only create if notifications table exists
            if (\Schema::hasTable('notifications')) {
                Notification::create($data);
            } else {
                Log::info('Notification would be sent: ' . $data['title'], $data);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage(), $data);
        }
    }

    protected function hasRecentNotification($model, $level, $type)
    {
        try {
            if (!\Schema::hasTable('notifications')) {
                return false;
            }

            $modelColumn = $model instanceof AgreementOverview ? 'agreement_overview_id' : 'document_request_id';
            
            return Notification::where($modelColumn, $model->id)
                ->where('type', $type)
                ->where(function($query) use ($level) {
                    $query->whereJsonContains('data->level', $level)
                          ->orWhereJsonContains('data->notification_type', $level);
                })
                ->where('created_at', '>', Carbon::now()->subHours(24))
                ->exists();
        } catch (\Exception $e) {
            Log::error('Error checking recent notifications: ' . $e->getMessage());
            return false;
        }
    }

    // Process custom notification rules
    protected function processCustomNotificationRules(DocumentRequest $document, MasterDocument $documentType)
    {
        $customRules = $documentType->notification_settings['custom_rules'] ?? [];
        
        foreach ($customRules as $rule) {
            $this->evaluateCustomRule($document, $documentType, $rule);
        }
    }

    protected function evaluateCustomRule(DocumentRequest $document, MasterDocument $documentType, $rule)
    {
        switch ($rule['trigger']) {
            case 'no_activity':
                $daysSinceUpdate = $document->updated_at ? Carbon::now()->diffInDays($document->updated_at) : 0;
                if ($daysSinceUpdate >= ($rule['value'] ?? 7)) {
                    $this->sendCustomRuleNotification($document, $documentType, $rule, $daysSinceUpdate);
                }
                break;
                
            case 'status_change':
                // Check if document has been in current status too long
                $daysInStatus = $document->updated_at ? Carbon::now()->diffInDays($document->updated_at) : 0;
                if ($daysInStatus >= ($rule['value'] ?? 5)) {
                    $this->sendCustomRuleNotification($document, $documentType, $rule, $daysInStatus);
                }
                break;
        }
    }

    protected function sendCustomRuleNotification(DocumentRequest $document, MasterDocument $documentType, $rule, $value)
    {
        $message = $rule['custom_message'] ?? "Custom notification for document '{$document->title}' - {$rule['trigger']}: {$value} days";
        $recipients = $this->getNotificationRecipients($document, $documentType);

        foreach ($recipients as $recipient) {
            $this->createNotification([
                'type' => 'custom_rule_notification',
                'recipient_nik' => $recipient['nik'] ?? null,
                'title' => 'Custom Notification: ' . ucfirst($rule['trigger']),
                'message' => $message,
                'document_request_id' => $document->id,
                'data' => [
                    'rule_trigger' => $rule['trigger'],
                    'rule_value' => $value,
                    'priority' => $rule['priority'] ?? 'medium',
                    'notification_type' => 'custom_rule'
                ]
            ]);
        }
    }
}
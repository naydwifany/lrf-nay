<?php
// app/Services/EmailService.php

namespace App\Services;

use App\Models\Notification;
use App\Mail\DocumentRequestMail;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send email notification
     */
    public function sendNotificationEmail(Notification $notification)
    {
        try {
            \Log::info('📧 Sending markdown email', [
                'notification_id' => $notification->id,
                'related_type' => $notification->related_type,
            ]);

            // ambil document
            $document = $notification->related;

            Mail::to('naylarizkadwifany@gmail.com')
                ->send(new DocumentRequestMail($document, 'submit'));

            return true;
        } catch (\Exception $e) {
            \Log::error('❌ Failed to send markdown email', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getTemplateForType(string $type): string
    {
        return match ($type) {
            Notification::TYPE_APPROVAL_REQUEST => 'emails.document-request-created',
            Notification::TYPE_DOCUMENT_COMPLETED => 'emails.document-request-completed',
            default => 'emails.document-request-created', // fallback
        };
    }

    /**
     * Get email template based on notification type
     */
    private function getEmailTemplate(string $type): string
    {
        return match($type) {
            'approval_request' => 'approval-request',
            'approval_approved' => 'approval-approved', 
            'approval_rejected' => 'approval-rejected',
            'discussion_started' => 'discussion-started',
            'discussion_closed' => 'discussion-closed',
            'document_completed' => 'document-completed',
            default => 'generic-notification'
        };
    }

    /**
     * Get email from NIK (customize based on your company's email pattern)
     */
    private function getEmailFromNik(string $nik): string
    {
        // Default pattern: nik@company.com
        // Customize this based on your company's email pattern
        return $nik . '@company.com';
    }

    /**
     * Send reminder email for pending approvals
     */
    public function sendApprovalReminder($approval, int $daysPending)
    {
        try {
            $recipientEmail = $this->getEmailFromNik($approval->approver_nik);
            
            $emailData = [
                'approver_name' => $approval->approver_name,
                'document_number' => $approval->documentRequest->nomor_dokumen ?? $approval->agreementOverview->nomor_dokumen,
                'days_pending' => $daysPending,
                'document_type' => $approval->documentRequest ? 'Document Request' : 'Agreement Overview'
            ];

            Mail::send('emails.approval-reminder', $emailData, function ($message) use ($recipientEmail, $approval) {
                $message->to($recipientEmail, $approval->approver_name)
                        ->subject('Reminder: Pending Approval Required');
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send reminder email: ' . $e->getMessage());
            return false;
        }
    }
}
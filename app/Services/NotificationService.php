<?php
// app/Services/NotificationService.php - FIXED VERSION

namespace App\Services;

use App\Models\Notification;
use App\Models\DocumentRequest;
use App\Models\DocumentApproval;
use App\Models\AgreementOverview;
use App\Models\User;

class NotificationService
{
    /**
     * Send approval request notification
     */
    public function sendApprovalRequest(DocumentRequest $documentRequest, User $approver): void
    {
        try {
            Notification::create([
                'recipient_nik' => $approver->nik,
                'recipient_name' => $approver->name,
                'sender_nik' => $documentRequest->nik,
                'sender_name' => $documentRequest->nama,
                'title' => 'New Document Approval Request',
                'message' => "Document '{$documentRequest->title}' requires your approval.",
                'type' => Notification::TYPE_APPROVAL_REQUEST,
                'related_type' => DocumentRequest::class,
                'related_id' => $documentRequest->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send approval request notification', [
                'document_id' => $documentRequest->id,
                'approver_nik' => $approver->nik,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send approval approved notification
     */
    public function sendApprovalApproved(DocumentRequest $documentRequest, DocumentApproval $approval): void
    {
        try {
            Notification::create([
                'recipient_nik' => $documentRequest->nik,
                'recipient_name' => $documentRequest->nama,
                'sender_nik' => $approval->approver_nik,
                'sender_name' => $approval->approver_name,
                'title' => 'Document Approved',
                'message' => "Your document '{$documentRequest->title}' has been approved by {$approval->approver_name}.",
                'type' => Notification::TYPE_APPROVAL_APPROVED,
                'related_type' => DocumentRequest::class,
                'related_id' => $documentRequest->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send approval approved notification', [
                'document_id' => $documentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send approval rejected notification
     */
    public function sendApprovalRejected(DocumentRequest $documentRequest, DocumentApproval $approval, string $reason): void
    {
        try {
            Notification::create([
                'recipient_nik' => $documentRequest->nik,
                'recipient_name' => $documentRequest->nama,
                'sender_nik' => $approval->approver_nik,
                'sender_name' => $approval->approver_name,
                'title' => 'Document Rejected',
                'message' => "Your document '{$documentRequest->title}' has been rejected by {$approval->approver_name}. Reason: {$reason}",
                'type' => Notification::TYPE_APPROVAL_REJECTED,
                'related_type' => DocumentRequest::class,
                'related_id' => $documentRequest->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send approval rejected notification', [
                'document_id' => $documentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send discussion started notification
     */
    public function sendDiscussionStarted(DocumentRequest $documentRequest): void
    {
        try {
            // Get all users who can participate in discussion
            $participants = User::whereIn('role', [
                'head_legal', 'general_manager', 'reviewer_legal', 
                'finance', 'head_finance', 'counterparty'
            ])->get();

            foreach ($participants as $participant) {
                Notification::create([
                    'recipient_nik' => $participant->nik,
                    'recipient_name' => $participant->name,
                    'sender_nik' => $documentRequest->nik,
                    'sender_name' => $documentRequest->nama,
                    'title' => 'Discussion Forum Started',
                    'message' => "Discussion forum for document '{$documentRequest->title}' is now open.",
                    'type' => Notification::TYPE_DISCUSSION_STARTED,
                    'related_type' => DocumentRequest::class,
                    'related_id' => $documentRequest->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send discussion started notifications', [
                'document_id' => $documentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send discussion closed notification
     */
    public function sendDiscussionClosed(DocumentRequest $documentRequest, User $headLegal): void
    {
        try {
            Notification::create([
                'recipient_nik' => $documentRequest->nik,
                'recipient_name' => $documentRequest->nama,
                'sender_nik' => $headLegal->nik,
                'sender_name' => $headLegal->name,
                'title' => 'Discussion Forum Closed',
                'message' => "Discussion forum for document '{$documentRequest->title}' has been closed. You can now create agreement overview.",
                'type' => Notification::TYPE_DISCUSSION_CLOSED,
                'related_type' => DocumentRequest::class,
                'related_id' => $documentRequest->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send discussion closed notification', [
                'document_id' => $documentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send document completed notification
     */
    public function sendDocumentCompleted(AgreementOverview $agreementOverview): void
    {
        try {
            $documentRequest = $agreementOverview->documentRequest;
            
            Notification::create([
                'recipient_nik' => $documentRequest->nik,
                'recipient_name' => $documentRequest->nama,
                'sender_nik' => null,
                'sender_name' => 'System',
                'title' => 'Document Process Completed',
                'message' => "Document '{$documentRequest->title}' has been fully approved and completed.",
                'type' => Notification::TYPE_DOCUMENT_COMPLETED,
                'related_type' => AgreementOverview::class,
                'related_id' => $agreementOverview->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send document completed notification', [
                'agreement_id' => $agreementOverview->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send new comment notification
     */
    public function sendNewComment(DocumentRequest $documentRequest, User $commenter, string $comment): void
    {
        try {
            // Get all participants in the discussion except the commenter
            $participants = User::whereIn('role', [
                'head_legal', 'general_manager', 'reviewer_legal', 
                'finance', 'head_finance', 'counterparty'
            ])->where('nik', '!=', $commenter->nik)->get();

            // Also notify the document requester if not the commenter
            if ($documentRequest->nik !== $commenter->nik) {
                $requester = User::where('nik', $documentRequest->nik)->first();
                if ($requester && !$participants->contains('nik', $requester->nik)) {
                    $participants->push($requester);
                }
            }

            foreach ($participants as $participant) {
                Notification::create([
                    'recipient_nik' => $participant->nik,
                    'recipient_name' => $participant->name,
                    'sender_nik' => $commenter->nik,
                    'sender_name' => $commenter->name,
                    'title' => 'New Discussion Comment',
                    'message' => "New comment from {$commenter->name} on document '{$documentRequest->title}': " . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : ''),
                    'type' => Notification::TYPE_DISCUSSION_STARTED, // Reuse this type for comments
                    'related_type' => DocumentRequest::class,
                    'related_id' => $documentRequest->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send new comment notifications', [
                'document_id' => $documentRequest->id,
                'commenter_nik' => $commenter->nik,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get unread notification count for user
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('recipient_nik', $user->nik)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): void
    {
        Notification::where('recipient_nik', $user->nik)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Mark specific notification as read
     */
    public function markAsRead(int $notificationId, User $user): void
    {
        Notification::where('id', $notificationId)
            ->where('recipient_nik', $user->nik)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Get recent notifications for user
     */
    public function getRecentNotifications(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('recipient_nik', $user->nik)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Send clarification request notification
     */
    public function sendClarificationRequest(DocumentRequest $documentRequest, User $approver, string $clarificationText): void
    {
        try {
            Notification::create([
                'recipient_nik' => $documentRequest->nik,
                'recipient_name' => $documentRequest->nama,
                'sender_nik' => $approver->nik,
                'sender_name' => $approver->name,
                'title' => 'Clarification Request',
                'message' => "Clarification needed for document '{$documentRequest->title}': {$clarificationText}",
                'type' => Notification::TYPE_APPROVAL_REQUEST, // Reuse this type
                'related_type' => DocumentRequest::class,
                'related_id' => $documentRequest->id,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send clarification request notification', [
                'document_id' => $documentRequest->id,
                'approver_nik' => $approver->nik,
                'error' => $e->getMessage()
            ]);
        }
    }
}
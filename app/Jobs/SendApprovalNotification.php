<?php
// app/Jobs/SendApprovalNotification.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DocumentApproval;
use App\Models\AgreementApproval;
use App\Services\NotificationService;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $approval;
    protected $approvalType;

    public function __construct($approval, string $approvalType = 'document')
    {
        $this->approval = $approval;
        $this->approvalType = $approvalType; // 'document' or 'agreement'
    }

    public function handle(NotificationService $notificationService): void
    {
        if ($this->approvalType === 'document') {
            $documentRequest = $this->approval->documentRequest;
            $approver = $this->approval->approver;
            
            $notificationService->sendApprovalRequest($documentRequest, $approver);
        } else {
            $agreementOverview = $this->approval->agreementOverview;
            $notificationService->sendAgreementApprovalRequest($agreementOverview, $this->approval);
        }
    }

    public function failed(\Exception $exception)
    {
        \Log::error('Failed to send approval notification: ' . $exception->getMessage());
    }
}
<?php
// app/Filament/User/Resources/MyAgreementOverviewResource/Pages/ViewMyAgreementOverview.php

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Forms;
use App\Services\DocumentWorkflowService;

class ViewMyAgreementOverview extends ViewRecord
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        $workflowService = app(DocumentWorkflowService::class);
        $canApprove = $workflowService->canUserApproveAgreementOverview(auth()->user(), $this->record);

        return [
            // Edit Action
            Actions\EditAction::make()
                ->visible(fn($record) => $record->canBeEdited()),

            // Submit Action - untuk user yang create
            Actions\Action::make('submit')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Agreement Overview')
                ->modalDescription('Are you sure you want to submit this agreement overview for approval? Once submitted, you cannot edit it until it\'s returned.')
                ->visible(fn($record) => $record->canBeSubmitted() && $record->nik === auth()->user()->nik)
                ->action(function ($record) {
                    try {
                        // Validation
                        if (empty($record->counterparty) || empty($record->deskripsi) || empty($record->resume)) {
                            Notification::make()
                                ->title('Validation Error')
                                ->body('Please complete all required fields: Counterparty, Description, and Executive Summary.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Update status to start workflow
                        $record->update([
                            'is_draft' => false,
                            'status' => 'pending_head',
                            'submitted_at' => now(),
                        ]);

                        // Log submission
                        \Log::info('AO Submitted for Approval', [
                            'ao_id' => $record->id,
                            'ao_number' => $record->nomor_dokumen,
                            'submitted_by' => auth()->user()->nik,
                            'status' => 'pending_head'
                        ]);

                        Notification::make()
                            ->title('✅ Agreement Overview Submitted')
                            ->body("AO {$record->nomor_dokumen} has been submitted for Head approval.")
                            ->success()
                            ->duration(5000)
                            ->send();

                    } catch (\Exception $e) {
                        \Log::error('Error submitting AO', [
                            'ao_id' => $record->id,
                            'error' => $e->getMessage()
                        ]);

                        Notification::make()
                            ->title('❌ Submission Error')
                            ->body('Failed to submit: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Approve Action - untuk approver
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form([
                    Forms\Components\Textarea::make('approval_comments')
                        ->label('Approval Comments')
                        ->placeholder('Optional: Add your approval comments')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Approve Agreement Overview')
                ->modalDescription('Are you sure you want to approve this agreement overview?')
                ->visible($canApprove)
                ->action(function ($record, array $data) {
                    $this->processApproval($record, 'approved', $data['approval_comments'] ?? '');
                }),

            // Reject Action - untuk approver
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->placeholder('Please explain why you are rejecting this agreement')
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Reject Agreement Overview')
                ->modalDescription('Please provide a reason for rejection.')
                ->visible($canApprove)
                ->action(function ($record, array $data) {
                    $this->processApproval($record, 'rejected', $data['rejection_reason']);
                }),

            // Request Revision Action - untuk approver
            Actions\Action::make('request_revision')
                ->label('Request Revision')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Forms\Components\Textarea::make('revision_notes')
                        ->label('Revision Notes')
                        ->placeholder('Please specify what needs to be revised')
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Request Revision')
                ->modalDescription('This will return the agreement to the creator for revision.')
                ->visible(fn($record) => $this->canUserApprove($record))
                ->action(function ($record, array $data) {
                    $this->processRevision($record, $data['revision_notes']);
                }),
        ];
    }

    // Check if current user can approve this record
    private function canUserApprove($record): bool
    {
        $userNik = auth()->user()->nik;
        $userRole = auth()->user()->jabatan ?? '';
        $status = $record->status;

        // Define approval hierarchy
        $approvalRules = [
            'pending_head' => ['HEAD', 'MANAGER', 'KEPALA'], // Head/Manager level
            'pending_gm' => ['GM', 'GENERAL MANAGER'], // GM level
            'pending_finance' => ['FINANCE', 'CFO'], // Finance team
            'pending_legal' => ['LEGAL', 'HUKUM'], // Legal team
            'pending_director1' => [$record->director1_nik], // Specific director 1
            'pending_director2' => [$record->director2_nik], // Specific director 2
        ];

        if (!isset($approvalRules[$status])) {
            return false;
        }

        $allowedApprovers = $approvalRules[$status];

        // Check if user NIK matches director NIK
        if (in_array($userNik, $allowedApprovers)) {
            return true;
        }

        // Check if user role contains approval keywords
        foreach ($allowedApprovers as $role) {
            if (stripos($userRole, $role) !== false) {
                return true;
            }
        }

        return false;
    }

    // Process approval/rejection
    private function processApproval($record, $decision, $comments)
    {
        try {
            $user = auth()->user();
            $currentStatus = $record->status;

            // Determine next status based on current status and decision
            $nextStatus = $this->getNextStatus($currentStatus, $decision);

            // Update record
            $record->update([
                'status' => $nextStatus,
                'completed_at' => $nextStatus === 'approved' ? now() : null,
            ]);

            // Create approval record
            \DB::table('agreement_approvals')->insert([
                'agreement_overview_id' => $record->id,
                'approver_nik' => $user->nik,
                'approver_name' => $user->name,
                'approval_type' => $this->getApprovalType($currentStatus),
                'status' => $decision,
                'comments' => $comments,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log approval
            \Log::info('AO Approval Decision', [
                'ao_id' => $record->id,
                'ao_number' => $record->nomor_dokumen,
                'approver' => $user->nik,
                'decision' => $decision,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'comments' => $comments
            ]);

            $actionText = $decision === 'approved' ? 'approved' : 'rejected';
            $nextStep = $nextStatus === 'approved' ? 'Agreement is fully approved!' : "Moved to: " . ucwords(str_replace('_', ' ', $nextStatus));

            Notification::make()
                ->title("✅ Agreement Overview {$actionText}")
                ->body("AO {$record->nomor_dokumen} has been {$actionText}. {$nextStep}")
                ->success()
                ->duration(5000)
                ->send();

        } catch (\Exception $e) {
            \Log::error('Error processing approval', [
                'ao_id' => $record->id,
                'error' => $e->getMessage(),
                'decision' => $decision
            ]);

            Notification::make()
                ->title('❌ Approval Error')
                ->body('Failed to process approval: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Process revision request
    private function processRevision($record, $revisionNotes)
    {
        try {
            $user = auth()->user();

            // Return to draft status for revision
            $record->update([
                'status' => 'draft',
                'is_draft' => true,
                'submitted_at' => null,
            ]);

            // Create revision record
            \DB::table('agreement_approvals')->insert([
                'agreement_overview_id' => $record->id,
                'approver_nik' => $user->nik,
                'approver_name' => $user->name,
                'approval_type' => $this->getApprovalType($record->status),
                'status' => 'revision_requested',
                'comments' => $revisionNotes,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Log::info('AO Revision Requested', [
                'ao_id' => $record->id,
                'ao_number' => $record->nomor_dokumen,
                'requester' => $user->nik,
                'notes' => $revisionNotes
            ]);

            Notification::make()
                ->title('🔄 Revision Requested')
                ->body("AO {$record->nomor_dokumen} has been returned for revision.")
                ->warning()
                ->duration(5000)
                ->send();

        } catch (\Exception $e) {
            \Log::error('Error requesting revision', [
                'ao_id' => $record->id,
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->title('❌ Revision Request Error')
                ->body('Failed to request revision: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Get next status in workflow
    private function getNextStatus($currentStatus, $decision): string
    {
        if ($decision === 'rejected') {
            return 'rejected';
        }

        // Approval workflow progression
        $workflowSteps = [
            'pending_head' => 'pending_gm',
            'pending_gm' => 'pending_finance',
            'pending_finance' => 'pending_legal',
            'pending_legal' => 'pending_director1',
            'pending_director1' => 'pending_director2',
            'pending_director2' => 'approved',
        ];

        return $workflowSteps[$currentStatus] ?? 'approved';
    }

    // Get approval type label
    private function getApprovalType($status): string
    {
        $typeMapping = [
            'pending_head' => 'Head Approval',
            'pending_gm' => 'GM Approval',
            'pending_finance' => 'Finance Approval',
            'pending_legal' => 'Legal Approval',
            'pending_director1' => 'Director 1 Approval',
            'pending_director2' => 'Director 2 Approval',
        ];

        return $typeMapping[$status] ?? 'Unknown Approval';
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();
        $statusBadge = match($record->status) {
            'draft' => '📝 Draft',
            'pending_head' => '👨‍💼 Pending Head',
            'pending_gm' => '🎯 Pending GM',
            'pending_finance' => '💰 Pending Finance',
            'pending_legal' => '⚖️ Pending Legal',
            'pending_director1' => '👔 Pending Director 1',
            'pending_director2' => '👔 Pending Director 2',
            'approved' => '✅ Approved',
            'rejected' => '❌ Rejected',
            default => '📄 Unknown Status'
        };
        
        return "{$statusBadge} Agreement Overview: {$record->nomor_dokumen}";
    }
}
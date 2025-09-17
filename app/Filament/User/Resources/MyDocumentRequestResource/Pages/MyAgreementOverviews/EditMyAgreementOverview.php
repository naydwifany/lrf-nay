<?php
// app/Filament/User/Resources/MyAgreementOverviewResource/Pages/EditMyAgreementOverview.php

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews;

use App\Filament\User\Resources\MyDocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Traits\DirectorManagementTrait;
use Filament\Forms;

class EditMyAgreementOverview extends EditRecord
{
    use DirectorManagementTrait;
    
    protected static string $resource = MyDocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $actions = [];

        // === BUTTONS FOR DRAFT STATUS ===
        if ($record->status === 'draft' && $record->nik === auth()->user()->nik) {
            
            $actions[] = Actions\Action::make('cancel_edit')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn() => static::getResource()::getUrl('index'))
                ->requiresConfirmation()
                ->modalHeading('Cancel Editing')
                ->modalDescription('Are you sure you want to cancel? Any unsaved changes will be lost.');
        }

        // === BUTTONS FOR PENDING STATUS (Approvers) ===
        elseif (in_array($record->status, ['pending_head', 'pending_gm', 'pending_finance', 'pending_legal', 'pending_director1', 'pending_director2'])) {
            
            // Only show approve/reject if user can approve at current step
            if ($this->canUserApproveAtCurrentStep($record)) {
                
                $actions[] = Actions\Action::make('approve')
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
                    ->action(function (array $data) {
                        $this->processApproval('approved', $data['approval_comments'] ?? '');
                    });

                $actions[] = Actions\Action::make('reject')
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
                    ->action(function (array $data) {
                        $this->processApproval('rejected', $data['rejection_reason']);
                    });

                $actions[] = Actions\Action::make('request_revision')
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
                    ->action(function (array $data) {
                        $this->processRevision($data['revision_notes']);
                    });

                $actions[] = Actions\Action::make('cancel_view')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->url(fn() => static::getResource()::getUrl('index'));
            }
        }

        // === READ-ONLY VIEW FOR COMPLETED/REJECTED ===
        elseif (in_array($record->status, ['approved', 'rejected'])) {
            $actions[] = Actions\Action::make('back_to_list')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => static::getResource()::getUrl('index'));
        }

        // Default view action for all other cases
        if (empty($actions)) {
            $actions[] = Actions\ViewAction::make();
        }

        return $actions;
    }

    // Override form to make it read-only for non-draft status
    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        
        // If draft and user is owner, show save and submit buttons
        if ($record->status === 'draft' && $record->nik === auth()->user()->nik) {
            return [
                $this->getSaveFormAction()
                    ->label('Save Changes')
                    ->icon('heroicon-o-check'),
                    
                Actions\Action::make('submit')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Agreement Overview')
                    ->modalDescription('Are you sure you want to submit this agreement overview for approval? Once submitted, you cannot edit it until it\'s returned.')
                    ->action(function () {
                        $this->submitForApproval();
                    }),
            ];
        }
        
        // For all other statuses, no form actions (read-only)
        return [];
    }

    // Check if form should be disabled (read-only)
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        
        // If not draft or not owner, make form read-only
        if ($record->status !== 'draft' || $record->nik !== auth()->user()->nik) {
            $this->form->disabled();
        }

        // Auto-fill director1 info if not set
        if (empty($data['director1_nik'])) {
            $user = auth()->user();
            $director1 = static::getDirector1FromDirektorat($user->direktorat ?? 'IT');
            
            $data['director1_nik'] = $director1['nik'];
            $data['director1_name'] = $director1['name'];
        }

        // Fill director1_name for display (since it's disabled)
        if (!empty($data['director1_nik']) && empty($data['director1_name'])) {
            $director1 = static::getDirector1FromDirektorat(auth()->user()->direktorat ?? 'IT');
            $data['director1_name'] = $director1['name'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        
        // Prevent saving if not editable
        if (!$record->canBeEdited() || $record->nik !== auth()->user()->nik) {
            Notification::make()
                ->title('Save Blocked')
                ->body('Cannot save changes to a submitted agreement overview.')
                ->danger()
                ->send();
            
            $this->halt();
        }

        // Auto-fill user info
        $user = auth()->user();
        $data = array_merge($data, [
            'nik' => $user->nik ?? $data['nik'],
            'nama' => $user->name ?? $data['nama'],
            'jabatan' => $user->jabatan ?? $data['jabatan'],
            'divisi' => $user->divisi ?? $data['divisi'],
            'direktorat' => $user->direktorat ?? $data['direktorat'],
            'level' => $user->level ?? $data['level'],
        ]);

        // Auto-fill director1 if not set
        if (empty($data['director1_nik'])) {
            $director1 = static::getDirector1FromDirektorat($user->direktorat ?? 'IT');
            $data['director1_nik'] = $director1['nik'];
            $data['director1_name'] = $director1['name'];
        }

        // Fill director2_name if director2_nik is set but name is empty
        if (!empty($data['director2_nik']) && empty($data['director2_name'])) {
            $director2 = static::getDirector2Details($data['director2_nik']);
            $data['director2_name'] = $director2['name'];
        }

        // Auto-generate nomor_dokumen if empty
        if (empty($data['nomor_dokumen'])) {
            $data['nomor_dokumen'] = static::generateAONumber();
        }

        // Ensure is_draft and status consistency
        if ($data['is_draft'] ?? true) {
            $data['status'] = 'draft';
            $data['submitted_at'] = null;
        }

        return $data;
    }

    // Custom action methods
    private function submitForApproval(): void
    {
        try {
            $record = $this->getRecord();
            
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

            // Redirect to view page
            $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));

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
    }

    private function processApproval($decision, $comments): void
    {
        try {
            $record = $this->getRecord();
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

            // Redirect to view page
            $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));

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

    private function processRevision($revisionNotes): void
    {
        try {
            $record = $this->getRecord();
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

            // Redirect to list
            $this->redirect(static::getResource()::getUrl('index'));

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

    // Check if current user can approve this record
    private function canUserApproveAtCurrentStep($record): bool
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

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Agreement Overview Updated')
            ->body('Your agreement overview has been saved successfully.');
    }

    // Helper methods (same as before)
    public static function getDirector1FromDirektorat(string $direktorat): array
    {
        $directorMapping = [
            'IT' => [
                'nik' => '14070619',
                'name' => 'Wiradi',
                'title' => 'Finance & Admin IT Director',
                'direktorat' => 'IT'
            ],
            'LEGAL' => [
                'nik' => '20050037',
                'name' => 'Widi Satya Chitra',
                'title' => 'Corporate Secretary, Legal & Business Development Director',
                'direktorat' => 'Legal'
            ],
            'EXECUTIVE' => [
                'nik' => '710144',
                'name' => 'Lyvia Mariana',
                'title' => 'Direktur Utama',
                'direktorat' => 'Executive'
            ],
        ];

        return $directorMapping[strtoupper($direktorat)] ?? $directorMapping['IT'];
    }

    public static function getDirector2Details($director2Selection): array
    {
        $directors = [
            '14070619' => [
                'nik' => '14070619',
                'name' => 'Wiradi - FA IT Director',
                'title' => 'Finance & Admin IT Director',
                'direktorat' => 'IT'
            ],
            '710144' => [
                'nik' => '710144',
                'name' => 'Lyvia Mariana - Direktur Utama',
                'title' => 'Direktur Utama',
                'direktorat' => 'Executive'
            ],
            '20050037' => [
                'nik' => '20050037',
                'name' => 'Widi Satya Chitra - Corporate Secretary, Legal & Business Development Director',
                'title' => 'Corporate Secretary, Legal & Business Development Director',
                'direktorat' => 'Legal'
            ],
        ];

        return $directors[$director2Selection] ?? [
            'nik' => 'DIR_UNKNOWN',
            'name' => 'Unknown Director',
            'title' => 'Unknown Title',
            'direktorat' => 'Unknown'
        ];
    }

    public static function generateAONumber(): string
    {
        try {
            $lastAO = \DB::table('agreement_overviews')->latest('id')->first();
            $partNumber = $lastAO ? ($lastAO->id + 1) : 1;

            $seqNumber = str_pad($partNumber, 4, '0', STR_PAD_LEFT);
            $month = date('m');
            $year = date('Y');

            return "AO/{$seqNumber}/{$month}/{$year}";
        } catch (\Exception $e) {
            $partNumber = rand(1000, 9999);
            $month = date('m');
            $year = date('Y');

            return "AO/{$partNumber}/{$month}/{$year}";
        }
    }
}
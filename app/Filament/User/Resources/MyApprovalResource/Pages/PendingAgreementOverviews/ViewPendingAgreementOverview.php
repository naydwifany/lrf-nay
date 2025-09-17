<?php
// app/Filament/User/Resources/PendingAgreementOverviewResource/Pages/ViewPendingAgreementOverview.php

namespace App\Filament\User\Resources\MyApprovalResource\Pages\PendingAgreementOverviews;

use App\Filament\User\Resources\MyApprovalResource;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewPendingAgreementOverview extends ViewRecord
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        $workflowService = app(DocumentWorkflowService::class);
        $canApprove = $workflowService->canUserApproveAgreementOverview(auth()->user(), $this->record);
        $user = auth()->user();

        return [
            /* approve/reject/send to rediscuss buttons moved to infolist in PendingAgreementOverviewResource.php
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($canApprove)
                ->requiresConfirmation()
                ->modalHeading('Approve Agreement Overview')
                ->modalDescription('Are you sure you want to approve this agreement overview?')
                ->modalSubmitActionLabel('Approve')
                ->form([
                    Forms\Components\Textarea::make('comments')
                        ->label('Approval Comments (Optional)')
                        ->rows(3)
                        ->placeholder('Add any comments or notes about your approval...')
                ])
                ->action(function (array $data) use ($workflowService) {
                    $workflowService->approveAgreementOverview(
                        $this->record, 
                        auth()->user(), 
                        $data['comments'] ?? 'Approved'
                    );
                    
                    Notification::make()
                        ->title('Agreement Overview Approved')
                        ->body('The agreement overview has been successfully approved and moved to the next stage.')
                        ->success()
                        ->send();
                        
                    return redirect()->route('filament.user.resources.pending-agreement-overviews.index');
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function () use ($user, $canApprove) {
                    // Exclude director1 & director2 from seeing the Reject button
                    return $canApprove && ! in_array($user->role, ['director1', 'director2']);
                })
                ->requiresConfirmation()
                ->modalHeading('Reject Agreement Overview')
                ->modalDescription('Please provide a reason for rejecting this agreement overview.')
                ->modalSubmitActionLabel('Reject')
                ->form([
                    Forms\Components\Textarea::make('comments')
                        ->label('Rejection Reason')
                        ->rows(4)
                        ->required()
                        ->placeholder('Please explain why you are rejecting this agreement overview...')
                ])
                ->action(function (array $data) use ($workflowService) {
                    $workflowService->rejectAgreementOverview(
                        $this->record, 
                        auth()->user(), 
                        $data['comments']
                    );
                    
                    Notification::make()
                        ->title('Agreement Overview Rejected')
                        ->body('The agreement overview has been rejected and the requester will be notified.')
                        ->warning()
                        ->send();
                        
                    return redirect()->route('filament.user.resources.pending-agreement-overviews.index');
                }),
            */

            Actions\Action::make('back_to_list')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => route('filament.user.resources.pending-agreement-overviews.index')),
        ];
    }

    public function getTitle(): string
    {
        return "Review: {$this->record->nomor_dokumen}";
    }

    public function getHeading(): string
    {
        return 'Agreement Overview Review';
    }

    public function getSubheading(): string
    {
        $workflowService = app(DocumentWorkflowService::class);
        $progress = $workflowService->getAgreementOverviewProgress($this->record);
        $statusLabel = \App\Models\AgreementOverview::getStatusOptions()[$this->record->status] ?? $this->record->status;
        
        return "Status: {$statusLabel} | Progress: {$progress}% | Submitted: {$this->record->submitted_at?->diffForHumans()}";
    }
}
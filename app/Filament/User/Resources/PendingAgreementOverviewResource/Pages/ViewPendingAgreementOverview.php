<?php
// app/Filament/User/Resources/PendingAgreementOverviewResource/Pages/ViewPendingAgreementOverview.php

namespace App\Filament\User\Resources\PendingAgreementOverviewResource\Pages;

use App\Filament\User\Resources\PendingAgreementOverviewResource;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewPendingAgreementOverview extends ViewRecord
{
    protected static string $resource = PendingAgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        $workflowService = app(DocumentWorkflowService::class);
        $canApprove = $workflowService->canUserApproveAgreementOverview(auth()->user(), $this->record);

        return [
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
                ->visible($canApprove)
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

            Actions\Action::make('rediscuss')
                ->label('Send to Re-discussion')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->visible($canApprove)
                ->requiresConfirmation()
                ->modalHeading('Send to Re-discussion')
                ->modalDescription('This will send the agreement overview back for further discussion.')
                ->modalSubmitActionLabel('Send to Re-discussion')
                ->form([
                    Forms\Components\Textarea::make('comments')
                        ->label('Discussion Points')
                        ->rows(4)
                        ->required()
                        ->placeholder('Please specify what needs to be discussed or clarified...')
                ])
                ->action(function (array $data) use ($workflowService) {
                    $workflowService->sendAgreementOverviewToRediscussion(
                        $this->record, 
                        auth()->user(), 
                        $data['comments']
                    );
                    
                    Notification::make()
                        ->title('Sent to Re-discussion')
                        ->body('The agreement overview has been sent for re-discussion.')
                        ->info()
                        ->send();
                        
                    return redirect()->route('filament.user.resources.pending-agreement-overviews.index');
                }),

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
<?php
// app/Filament/Admin/Resources/AgreementOverviewResource/Pages/ViewAgreementOverview.php

namespace App\Filament\Admin\Resources\AgreementOverviewResource\Pages;

use App\Filament\Admin\Resources\AgreementOverviewResource;
use App\Models\AgreementOverview;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewAgreementOverview extends ViewRecord
{
    protected static string $resource = AgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        $workflowService = app(DocumentWorkflowService::class);
        $canApprove = $workflowService->canUserApproveAgreementOverview(auth()->user(), $this->record);
        $user = auth()->user();

        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->is_draft),

            /* approve/reject/send to rediscussion buttons move to below at infolist in AgreementOverviewResource.php
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Agreement Overview')
                ->modalDescription('Are you sure you want to approve this agreement overview?')
                ->modalSubmitActionLabel('Approve')
                ->visible(fn (AgreementOverview $record) =>
                    auth()->user()->role === 'director' &&
                    in_array($record->status, [
                        AgreementOverview::STATUS_PENDING_DIRECTOR1,
                        AgreementOverview::STATUS_PENDING_DIRECTOR2,
                    ])
                )
                ->action(function (AgreementOverview $record, array $data) {
                    $workflowService = app(DocumentWorkflowService::class);

                    $workflowService->approveAgreementOverview(
                        $record,
                        auth()->user(),
                        $data['approval_comments'] ?? 'Approved'
                    );

                    Notification::make()
                        ->title('Agreement Overview Approved')
                        ->body('The agreement overview has been successfully approved.')
                        ->success()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('approval_comments')
                        ->label('Approval Comments')
                        ->rows(3)
                        ->helperText('Optional: Add your comments for this approval'),
                ]),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function () use ($user, $canApprove) {
                    // Exclude director1 & director2 from seeing the Reject button
                    return $canApprove && ! $user->role === 'director';
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

            Actions\Action::make('rediscuss')
                ->label('Send to Re-discussion')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send to Re-discussion')
                ->modalDescription('This will send the agreement overview back for further discussion.')
                ->modalSubmitActionLabel('Send to Re-discussion')
                ->visible(fn (AgreementOverview $record) =>
                    auth()->user()->role === 'director' &&
                    in_array($record->status, [
                        AgreementOverview::STATUS_PENDING_DIRECTOR1,
                        AgreementOverview::STATUS_PENDING_DIRECTOR2,
                    ])
                )
                ->action(function (AgreementOverview $record, array $data) {
                    $workflowService = app(DocumentWorkflowService::class);

                    $workflowService->sendAgreementOverviewToRediscussion(
                $record,
                        auth()->user(),
                        $data['rediscussion_comments'] ?? 'Sent back to discussion'
                    );

                    Notification::make()
                        ->title('Agreement Overview Sent Back')
                        ->body('The agreement overview has been sent back to forum discussion.')
                        ->warning()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('rediscussion_comments')
                        ->label('Rediscussion Comments')
                        ->rows(3)
                        ->helperText('Optional: Add your comments for this rediscussion'),
                ]),
            */
                
            Actions\Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Agreement Overview')
                ->modalDescription('Are you sure you want to submit this agreement overview for approval? You won\'t be able to edit it after submission.')
                ->action(function () {
                    try {
                        $this->record->update([
                            'is_draft' => false,
                            'status' => 'submitted',
                            'submitted_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Agreement Overview submitted successfully')
                            ->body('Your agreement overview has been submitted for approval.')
                            ->success()
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error submitting agreement overview')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->is_draft && $this->record->status === 'draft'),
                
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    // PDF generation logic here
                    return response()->download(
                        storage_path('app/agreements/' . $this->record->id . '.pdf'),
                        'Agreement_Overview_' . $this->record->nomor_dokumen . '.pdf'
                    );
                })
                ->visible(fn() => !$this->record->is_draft),
        ];
    }
}
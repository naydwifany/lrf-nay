<?php

namespace App\Filament\Admin\Resources\DocumentRequestResource\Pages;

use App\Filament\Admin\Resources\DocumentRequestResource;
use App\Models\DocumentApproval;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentRequest extends ViewRecord
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Edit Action
            Actions\EditAction::make()
                ->visible(function () {
                    $user = auth()->user();
                    $record = $this->record;
                    
                    if (!$user) return false;
                    
                    // Only allow editing drafts by creator or admin
                    return ($record->status === 'draft' && $record->nik === $user->nik) || 
                           in_array($user->role ?? '', ['admin', 'super_admin']);
                }),

            // Submit Action (for drafts)
            Actions\Action::make('submit')
                ->label('Submit Document')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->action(function () {
                    try {
                        $workflowService = app(DocumentWorkflowService::class);
                        $workflowService->submitDocument($this->record, auth()->user());
                        
                        Notification::make()
                            ->title('Document submitted successfully')
                            ->body('Your document has been submitted for approval.')
                            ->success()
                            ->send();
                            
                        // Refresh the page to show updated status
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error submitting document')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Submit Document')
                ->modalDescription('Are you sure you want to submit this document for approval? You won\'t be able to edit it after submission.')
                ->visible(function () {
                    $user = auth()->user();
                    $record = $this->record;
                    
                    if (!$user) return false;
                    
                    return $record->status === 'draft' && 
                           $record->nik === $user->nik &&
                           $record->title && 
                           $record->description;
                }),

            /* approve/reject move to below infolist on DocumentRequestResource.php
            // Approve Action
            Actions\Action::make('approve')
                ->label('Approve Document')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size('lg')
                ->form([
                    Forms\Components\Section::make('Document Approval')
                        ->description('Please review the document and provide your approval comments')
                        ->schema([
                            Forms\Components\Textarea::make('approval_comments')
                                ->label('Approval Comments')
                                ->rows(4)
                                ->placeholder('Add your comments for this approval (optional)')
                                ->helperText('Your comments will be visible in the approval history'),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        $workflowService = app(DocumentWorkflowService::class);
                        $workflowService->approve(
                            $this->record, 
                            auth()->user(), 
                            $data['approval_comments'] ?? null
                        );
                        
                        Notification::make()
                            ->title('Document approved successfully')
                            ->body('Document has been approved and moved to the next stage.')
                            ->success()
                            ->send();
                            
                         // Redirect to the discussion forum after approval
                        return redirect()->to(DocumentRequestResource::getUrl('discussion', ['record' => $this->record]));
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error approving document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Document')
                    ->modalDescription('Are you sure you want to approve this document? This will move it to the next approval stage.')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(function () {
                        $user = auth()->user();
                        $record = $this->record;
                        
                        if (!$user || !$user->nik) return false;
                        
                        // Check if user has pending approval for this document
                        return DocumentApproval::where('document_request_id', $record->id)
                            ->where('approver_nik', $user->nik)
                            ->where('status', 'pending')
                            ->exists();
                    }),

            // Reject Action
            Actions\Action::make('reject')
                ->label('Reject Document')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->size('lg')
                ->form([
                    Forms\Components\Section::make('Document Rejection')
                        ->description('Please provide a clear reason for rejecting this document')
                        ->schema([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->rows(4)
                                ->placeholder('Please provide a clear and detailed reason for rejection')
                                ->helperText('This reason will be sent to the document creator'),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        $workflowService = app(DocumentWorkflowService::class);
                        $workflowService->rejectDocument(
                            $this->record, 
                            auth()->user(), 
                            $data['rejection_reason']
                        );
                        
                        Notification::make()
                            ->title('Document rejected')
                            ->body('Document has been rejected and requester will be notified.')
                            ->success()
                            ->send();
                            
                        // Refresh the page to show updated status
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error rejecting document')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Reject Document')
                ->modalDescription('Are you sure you want to reject this document? This action cannot be undone.')
                ->modalSubmitActionLabel('Reject Document')
                ->visible(function () {
                    $user = auth()->user();
                    $record = $this->record;
                    
                    if (!$user || !$user->nik) return false;
                    
                    // Check if user has pending approval for this document
                    return DocumentApproval::where('document_request_id', $record->id)
                        ->where('approver_nik', $user->nik)
                        ->where('status', 'pending')
                        ->exists();
                }),
            */

            // Approval History Action
            Actions\Action::make('approval_history')
                ->label('View Approval History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalContent(function () {
                    $approvals = $this->record->approvals()->with('approver')->orderBy('created_at')->get();
                    
                    $content = '<div class="space-y-4">';
                    
                    if ($approvals->isEmpty()) {
                        $content .= '<div class="text-center py-8">
                            <p class="text-gray-500 text-lg">No approval history yet.</p>
                            <p class="text-gray-400 text-sm">Approval history will appear here once the document is submitted.</p>
                        </div>';
                    } else {
                        foreach ($approvals as $approval) {
                            $statusColor = match($approval->status) {
                                'pending' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
                                'approved' => 'text-green-600 bg-green-50 border-green-200', 
                                'rejected' => 'text-red-600 bg-red-50 border-red-200',
                                default => 'text-gray-600 bg-gray-50 border-gray-200'
                            };
                            
                            $statusIcon = match($approval->status) {
                                'pending' => '⏳',
                                'approved' => '✅',
                                'rejected' => '❌',
                                default => '❓'
                            };
                            
                            $approverName = $approval->approver->name ?? 'Unknown';
                            $approvalType = ucfirst(str_replace('_', ' ', $approval->approval_type));
                            $date = $approval->approved_at ? $approval->approved_at->format('M j, Y H:i') : 'Pending';
                            $comments = $approval->comments ?: 'No comments provided';
                            
                            $content .= "
                                <div class='border rounded-lg p-4 bg-white dark:bg-gray-800 shadow-sm'>
                                    <div class='flex justify-between items-start mb-3'>
                                        <div>
                                            <h4 class='font-semibold text-gray-900 dark:text-white-900 text-lg mb-1'>{$approverName}</h4>
                                            <p class='text-sm text-gray-900 dark:text-white-900 font-medium mb-2'>{$approvalType}</p>
                                        </div>
                                        <span class='inline-flex items-center px-3 py-1 text-sm font-medium rounded-full border {$statusColor}'>
                                            {$statusIcon} " . ucfirst($approval->status) . "
                                        </span>
                                    </div>
                                    <div class='space-y-2'>
                                        <p class='text-sm text-gray-700 dark:text-white mb-2'><strong>📅 Date:</strong> {$date}</p>
                                        <p class='text-sm text-gray-700 dark:text-white mb-2'><strong>💬 Comments:</strong></p>
                                        <div class='bg-gray-200 dark:bg-gray-900 rounded p-3 text-sm text-gray-600 dark:text-white-900'>{$comments}</div>
                                    </div>
                                </div>
                            ";
                        }
                    }
                    
                    $content .= '</div>';
                    
                    return new \Illuminate\Support\HtmlString($content);
                })
                ->modalHeading('Document Approval History')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->slideOver()
                ->visible(fn() => $this->record->status !== 'draft'),
            Actions\EditAction::make(),
        
        // ADD THIS ACTION
        Actions\Action::make('view_discussion')
            ->label('View Discussion')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('primary')
            ->visible(fn () => $this->record->status === 'discussion')
            ->url(fn () => DocumentRequestResource::getUrl('discussion', ['record' => $this->record])),
            
        
            // Delete Action (only for drafts)
            Actions\DeleteAction::make()
                ->visible(function () {
                    $user = auth()->user();
                    $record = $this->record;
                    
                    if (!$user) return false;
                    
                    // Only allow deleting drafts by creator or admin
                    return ($record->status === 'draft' && $record->nik === $user->nik) || 
                           in_array($user->role ?? '', ['admin', 'super_admin']);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add any widgets if needed
        ];
    }

        

    protected function getFooterWidgets(): array
    {
        return [
            // Add any widgets if needed
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Add any additional logic when the page is mounted
        // For example, mark as viewed, log access, etc.
    }
}
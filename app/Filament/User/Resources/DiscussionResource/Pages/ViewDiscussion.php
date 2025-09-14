<?php

// app/Filament/User/Resources/DiscussionResource/Pages/ViewDiscussion.php

namespace App\Filament\User\Resources\DiscussionResource\Pages;

use App\Filament\User\Resources\DiscussionResource;
use App\Services\DocumentDiscussionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\WithFileUploads;

class ViewDiscussion extends ViewRecord
{
    use WithFileUploads;

    protected static string $resource = DiscussionResource::class;
    
    // IMPORTANT: Set the correct view path for USER panel
    protected static string $view = 'filament.user.resources.discussion.pages.view-discussion';

    public string $newComment = '';
    public array $attachments = [];
    
    // Reply functionality
    public ?int $replyingTo = null;
    public ?object $replyingToComment = null;
    public string $replyContent = '';
    public array $replyAttachments = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('close_discussion')
                ->label('Close Discussion')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(function () {
                    $user = auth()->user();
                    return $user->role === 'head_legal' && 
                           !$this->isDiscussionClosed();
                })
                ->disabled(fn() => !(
                    ($this->discussionStats['finance_participated'] ?? false) || 
                    ($this->discussionStats['head_finance_participated'] ?? false)
                ))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Closure Reason')
                        ->required()
                        ->placeholder('Please provide reason for closing the discussion...'),
                ])
                ->action(function (array $data) {
                    try {
                        app(DocumentDiscussionService::class)->closeDiscussion(
                            $this->record,
                            auth()->user(),
                            $data['reason']
                        );

                        Notification::make()
                            ->title('Discussion Closed')
                            ->body('The discussion has been closed and document moved to agreement creation phase.')
                            ->success()
                            ->send();

                        return redirect()->to(DiscussionResource::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalDescription(function () {
                    return $this->canCloseDiscussion() 
                        ? 'Are you sure you want to close this discussion? This will move the document to agreement creation phase and cannot be undone.'
                        : 'Discussion cannot be closed yet. Finance team must participate first.';
                }),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->refreshComments();
                    
                    Notification::make()
                        ->title('Refreshed')
                        ->body('Discussion has been updated with latest comments.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function addComment(): void
    {
        $this->validate([
            'newComment' => 'required|string|min:3|max:5000',
            'attachments.*' => 'file|max:10240|mimes:jpeg,jpg,png,gif,pdf,doc,docx,xls,xlsx,txt'
        ]);

        try {
            \Log::info('User Panel - Adding comment', [
                'document_id' => $this->record->id,
                'user_nik' => auth()->user()->nik,
                'comment_length' => strlen($this->newComment),
                'attachments_count' => count($this->attachments),
                'panel' => 'user'
            ]);

            // Convert Livewire temporary files to proper format
            $processedAttachments = [];
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $attachment) {
                    if ($attachment instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $processedAttachments[] = $attachment;
                    }
                }
            }

            app(DocumentDiscussionService::class)->addComment(
                $this->record,
                auth()->user(),
                $this->newComment,
                $processedAttachments
            );

            // Mark user as having seen comments
            app(DocumentDiscussionService::class)->markUserSeenComments(auth()->user());

            Notification::make()
                ->title('Message Sent')
                ->body('Your message has been posted successfully.')
                ->success()
                ->send();

            // Reset form
            $this->newComment = '';
            $this->attachments = [];

            $this->refreshComments();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
                
            \Log::error('User Panel - Error adding comment: ' . $e->getMessage(), [
                'document_id' => $this->record->id,
                'user_nik' => auth()->user()->nik,
                'trace' => $e->getTraceAsString(),
                'panel' => 'user'
            ]);
        }
    }

    public function replyTo(int $commentId): void
    {
        $this->replyingTo = $commentId;
        $this->replyingToComment = $this->record->comments()->find($commentId);
        $this->replyContent = '';
        $this->replyAttachments = [];
    }

    public function cancelReply(): void
    {
        $this->replyingTo = null;
        $this->replyingToComment = null;
        $this->replyContent = '';
        $this->replyAttachments = [];
    }

    public function submitReply(): void
    {
        $this->validate([
            'replyContent' => 'required|string|min:1|max:5000',
            'replyAttachments.*' => 'file|max:10240|mimes:jpeg,jpg,png,gif,pdf,doc,docx,xls,xlsx,txt'
        ]);

        try {
            // Create reply comment with parent_id
            $this->record->comments()->create([
                'user_id' => auth()->id(),
                'user_nik' => auth()->user()->nik,
                'user_name' => auth()->user()->name,
                'user_role' => auth()->user()->role,
                'comment' => $this->replyContent,
                'parent_id' => $this->replyingTo,
                'is_forum_closed' => false,
            ]);

            Notification::make()
                ->title('Reply Sent')
                ->body('Your reply has been posted successfully.')
                ->success()
                ->send();

            $this->cancelReply();
            $this->refreshComments();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    // UPDATED: getComments method dengan newest first ordering
    public function getComments()
{
    try {
        \Log::info('Loading comments with attachments...');
        
        $comments = $this->record->comments()
            ->whereNull('parent_id')
            ->with([
                'attachmentFiles', // Pakai relationship yang benar
                'replies' => function($query) {
                    $query->with('attachmentFiles')
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();
            
        \Log::info('Comments loaded', [
            'comments_count' => $comments->count(),
            'comments_detail' => $comments->map(function($comment) {
                return [
                    'comment_id' => $comment->id,
                    'user_name' => $comment->user_name,
                    'attachments_count' => $comment->attachmentFiles ? $comment->attachmentFiles->count() : 0,
                ];
            })->toArray()
        ]);
        
        return $comments;
        
    } catch (\Exception $e) {
        \Log::error('Error loading comments: ' . $e->getMessage());
        return collect();
    }
}

    public function refreshComments(): void
    {
        $this->record = $this->record->fresh();
        
        // UPDATED: Log refresh with ordering info
        \Log::info('User Panel - Comments refreshed', [
            'document_id' => $this->record->id,
            'panel' => 'user',
            'ordering' => 'newest_first',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        try {
            app(DocumentDiscussionService::class)->deleteAttachment($attachmentId, auth()->user());

            Notification::make()
                ->title('File Deleted')
                ->body('The attachment has been deleted successfully.')
                ->success()
                ->send();

            $this->refreshComments();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getRoleColor(string $role): string
    {
        return match($role) {
            'head_legal' => 'red',
            'general_manager', 'head' => 'purple',
            'finance' => 'green',
            'reviewer_legal', 'admin_legal' => 'blue',
            'system' => 'gray',
            default => 'gray'
        };
    }

    public function mount($record): void
    {
        parent::mount($record);
        
        \Log::info('User Panel - ViewDiscussion mounted', [
            'document_id' => $record,
            'user_nik' => auth()->user()->nik,
            'panel' => 'user',
            'ordering' => 'newest_first'
        ]);
        
        // Mark user as having seen comments when they view the discussion
        app(DocumentDiscussionService::class)->markUserSeenComments(auth()->user());
    }

    // UPDATED: getViewData dengan informasi ordering
    protected function getViewData(): array
    {
        try {
            \Log::info('User Panel - Getting view data', [
                'document_id' => $this->record->id,
                'panel' => 'user',
                'ordering' => 'newest_first'
            ]);
            
            $service = app(DocumentDiscussionService::class);
            
            return array_merge(parent::getViewData(), [
                'discussionStats' => $service->getDiscussionStats($this->record),
                'canParticipate' => $service->canUserParticipate(auth()->user()),
                'participants' => $service->getDiscussionParticipants($this->record),
                'panel' => 'user',
                'commentOrder' => 'newest_first', // NEW: Add ordering info for view
            ]);
        } catch (\Exception $e) {
            \Log::error('User Panel - Error in ViewDiscussion getViewData: ' . $e->getMessage(), [
                'document_id' => $this->record->id,
                'panel' => 'user'
            ]);
            
            return array_merge(parent::getViewData(), [
                'discussionStats' => [
                    'total_comments' => $this->record->comments()->count(),
                    'total_attachments' => 0,
                    'participants_count' => 0,
                    'finance_participated' => false,
                    'is_closed' => false,
                ],
                'canParticipate' => true,
                'participants' => [],
                'panel' => 'user',
                'commentOrder' => 'newest_first',
            ]);
        }
    }

    // Helper methods
    private function isDiscussionClosed(): bool
    {
        return $this->record->comments()
            ->where('is_forum_closed', true)
            ->exists();
    }

    private function canCloseDiscussion(): bool
    {
        try {
            $service = app(DocumentDiscussionService::class);
            return $service->canCloseDiscussion($this->record);
        } catch (\Exception $e) {
            return false;
        }
    }

    // UPDATED: Auto refresh dengan newest first
    public function poll()
    {
        $this->refreshComments();
        
        \Log::debug('User Panel - Auto refresh poll', [
            'document_id' => $this->record->id,
            'panel' => 'user',
            'ordering' => 'newest_first'
        ]);
    }
}
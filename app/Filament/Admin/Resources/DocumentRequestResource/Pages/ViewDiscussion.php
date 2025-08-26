<?php

// app/Filament/Admin/Resources/DocumentRequestResource/Pages/ViewDiscussion.php
// SIMPLE VERSION

namespace App\Filament\Admin\Resources\DocumentRequestResource\Pages;

use App\Filament\Admin\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\DocumentComment;
use App\Services\DocumentDiscussionService;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Livewire\WithFileUploads;

class ViewDiscussion extends ViewRecord
{
    use WithFileUploads;

    protected static string $resource = DocumentRequestResource::class;
    protected static string $view = 'filament.admin.resources.document-request-resource.pages.view-discussion';

    // Properties
    public $newComment = '';
    public $newCommentAttachments = [];
    public $replyComment = '';
    public $showReplyForm = [];

    // View data - MAKE THESE PUBLIC
    public $discussionStats = [];
    public $discussionTimeline = [];
    public $canParticipate = false;
    public $isDiscussionClosed = false;

    protected $rules = [
        'newComment' => 'required|string|min:1|max:5000',
        'newCommentAttachments.*' => 'nullable|file|max:10240',
        'replyComment' => 'required|string|min:1|max:5000',
    ];

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->loadData();
        
        // Simple permission check
        $user = auth()->user();
        $service = app(DocumentDiscussionService::class);
        
        if (!$service->canUserParticipate($user)) {
            Notification::make()
                ->title('Access Denied')
                ->body('Your role is not authorized for discussions')
                ->danger()
                ->send();
                
            redirect()->to(DocumentRequestResource::getUrl('view', ['record' => $this->record]));
            return;
        }
    }

    public function loadData(): void
    {
        try {
            $service = app(DocumentDiscussionService::class);
            $user = auth()->user();
            
            $this->discussionStats = $service->getDiscussionStats($this->record);
            $this->discussionTimeline = $service->getDiscussionTimeline($this->record);
            $this->canParticipate = $service->canUserParticipate($user);
            $this->isDiscussionClosed = $this->record->isDiscussionClosed();

        } catch (\Exception $e) {
            \Log::error('Error loading discussion data: ' . $e->getMessage());
            
            // Set defaults
            $this->discussionStats = [
                'total_comments' => 0,
                'total_attachments' => 0,
                'participants_count' => 0,
                'finance_participated' => false,
                'is_closed' => false,
                'can_be_closed' => false,
            ];
            $this->discussionTimeline = [];
            $this->canParticipate = false;
            $this->isDiscussionClosed = true;
        }
    }

    // ✅ CRITICAL: Make record available as public property
    public function getRecordProperty()
    {
        return $this->record;
    }

    // Add getter methods for view
    public function getDocumentTitle(): string
    {
        return $this->record->title ?? 'Unknown Document';
    }

    public function getDocumentId(): int
    {
        return $this->record->id ?? 0;
    }

    public function getRequesterName(): string
    {
        return $this->record->nama ?? 'Unknown';
    }

    public function getDivision(): string
    {
        return $this->record->divisi ?? 'Unknown';
    }

    public function getDocumentStatus(): string
    {
        return $this->record->status ?? 'unknown';
    }

    public function addComment(): void
    {
        \Log::info('Adding comment', [
            'comment' => $this->newComment,
            'files' => count($this->newCommentAttachments ?? [])
        ]);

        $this->validate(['newComment' => 'required|string|min:1|max:5000']);

        if (empty(trim($this->newComment))) {
            Notification::make()
                ->title('Comment cannot be empty')
                ->danger()
                ->send();
            return;
        }

        try {
            $service = app(DocumentDiscussionService::class);
            
            $attachments = [];
            if (!empty($this->newCommentAttachments)) {
                foreach ($this->newCommentAttachments as $file) {
                    if ($file) {
                        $attachments[] = $file;
                    }
                }
            }

            $comment = $service->addComment(
                $this->record,
                auth()->user(),
                trim($this->newComment),
                $attachments
            );

            // Reset form
            $this->reset(['newComment', 'newCommentAttachments']);
            
            Notification::make()
                ->title('Comment added!')
                ->success()
                ->send();

            $this->loadData();

        } catch (\Exception $e) {
            \Log::error('Error adding comment: ' . $e->getMessage());

            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function replyToComment($commentId): void
    {
        if (empty(trim($this->replyComment))) {
            Notification::make()
                ->title('Reply cannot be empty')
                ->danger()
                ->send();
            return;
        }

        try {
            $reply = $this->record->comments()->create([
                'parent_id' => $commentId,
                'user_id' => auth()->id(),
                'user_nik' => auth()->user()->nik,
                'user_name' => auth()->user()->name,
                'user_role' => auth()->user()->role,
                'comment' => trim($this->replyComment),
            ]);

            $this->replyComment = '';
            $this->showReplyForm[$commentId] = false;
            
            Notification::make()
                ->title('Reply added!')
                ->success()
                ->send();

            $this->loadData();

        } catch (\Exception $e) {
            \Log::error('Error adding reply: ' . $e->getMessage());

            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function showReplyForm($commentId): void
    {
        $this->showReplyForm[$commentId] = true;
        $this->replyComment = '';
    }

    public function hideReplyForm($commentId): void
    {
        $this->showReplyForm[$commentId] = false;
        $this->replyComment = '';
    }

    public function toggleCommentResolution($commentId): void
    {
        try {
            $comment = DocumentComment::findOrFail($commentId);
            
            if (!in_array(auth()->user()->role, ['head_legal', 'general_manager'])) {
                throw new \Exception('Not authorized to resolve comments');
            }
            
            $isResolved = !$comment->is_resolved;
            
            $comment->update([
                'is_resolved' => $isResolved,
                'resolved_by' => $isResolved ? auth()->id() : null,
                'resolved_at' => $isResolved ? now() : null,
            ]);
            
            Notification::make()
                ->title($isResolved ? 'Comment resolved' : 'Comment unresolved')
                ->success()
                ->send();

            $this->loadData();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Override to provide data to view
    protected function getViewData(): array
    {
        return [
            'discussionStats' => $this->discussionStats,
            'discussionTimeline' => $this->discussionTimeline,
            'canParticipate' => $this->canParticipate,
            'isDiscussionClosed' => $this->isDiscussionClosed,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_document')
                ->label('← Back')
                ->color('gray')
                ->url(fn() => DocumentRequestResource::getUrl('view', ['record' => $this->record])),

            Actions\Action::make('close_discussion')
                ->label('Close Discussion')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('closure_reason')
                        ->label('Reason (Optional)')
                        ->rows(2)
                ])
                ->action(function (array $data) {
                    try {
                        $service = app(DocumentDiscussionService::class);
                        $service->closeDiscussion(
                            $this->record, 
                            auth()->user(), 
                            $data['closure_reason'] ?? null
                        );
                        
                        Notification::make()
                            ->title('Discussion closed!')
                            ->success()
                            ->send();
                            
                        return redirect()->to(DocumentRequestResource::getUrl('view', ['record' => $this->record]));
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn() => auth()->user()->role === 'head_legal' && $this->record->status === 'in_discussion')
                ->disabled(fn() => !($this->discussionStats['finance_participated'] ?? false)),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(fn() => $this->loadData()),
        ];
    }
}
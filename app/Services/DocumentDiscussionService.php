<?php

// app/Services/DocumentDiscussionService.php
// MINIMAL CHANGES - Added Finance notification features only

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentComment;
use App\Models\DocumentCommentAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentDiscussionService
{
    public function addComment($documentRequest, $user, $comment, $attachments = [])
    {
        \Log::info('=== SERVICE ADD COMMENT ===');
        \Log::info('User: ' . $user->name);
        \Log::info('Comment: ' . substr($comment, 0, 50));
        \Log::info('Attachments count: ' . count($attachments));

        try {
            DB::beginTransaction();

            // Create comment record
            $commentRecord = $documentRequest->comments()->create([
                'user_id' => $user->id,
                'user_nik' => $user->nik,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'comment' => $comment,
                'is_forum_closed' => false,
            ]);

            \Log::info('Comment created with ID: ' . $commentRecord->id);

            // Process attachments
            if (!empty($attachments)) {
                \Log::info('Processing attachments...');
                
                foreach ($attachments as $index => $attachment) {
                    try {
                        \Log::info("Processing attachment {$index}");
                        
                        if ($attachment instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $originalName = $attachment->getClientOriginalName();
                            $extension = $attachment->getClientOriginalExtension();
                            $size = $attachment->getSize();
                            
                            \Log::info("File: {$originalName}, Size: {$size}, Ext: {$extension}");
                            
                            // Generate unique filename
                            $filename = Str::uuid() . '.' . $extension;
                            
                            // FIXED: Create directory structure properly
                            $directory = 'discussion-attachments/' . $commentRecord->id;
                            
                            \Log::info("Storing to: {$directory}/{$filename}");
                            
                            // Store file - Laravel akan create folder otomatis jika pakai storeAs
                            $path = $attachment->storeAs($directory, $filename, 'public');
                            
                            if ($path) {
                                \Log::info("✅ File stored at: {$path}");
                                
                                // Verify file exists immediately
                                $fullPath = storage_path('app/public/' . $path);
                                if (file_exists($fullPath)) {
                                    \Log::info("✅ File verified exists: {$fullPath}");
                                    
                                    // Create attachment record
                                    $attachmentRecord = $commentRecord->attachmentFiles()->create([
                                        'original_filename' => $originalName,
                                        'filename' => $filename,
                                        'file_path' => $path,
                                        'file_size' => $size,
                                        'mime_type' => $attachment->getMimeType(),
                                        'uploaded_by_nik' => $user->nik,
                                        'uploaded_by_name' => $user->name,
                                    ]);
                                    
                                    \Log::info("✅ Attachment record created with ID: " . $attachmentRecord->id);
                                    
                                } else {
                                    \Log::error("❌ File NOT found after upload: {$fullPath}");
                                    throw new \Exception("File upload failed - file not found after storage");
                                }
                            } else {
                                \Log::error("❌ Failed to store file - storeAs returned false");
                                throw new \Exception("File storage failed");
                            }
                            
                        } else {
                            \Log::error("❌ Invalid attachment type: " . get_class($attachment));
                            throw new \Exception("Invalid file type");
                        }
                        
                    } catch (\Exception $e) {
                        \Log::error("❌ Error processing attachment {$index}: " . $e->getMessage());
                        throw $e; // Re-throw to stop processing
                    }
                }
            }

            DB::commit();
            \Log::info('✅ Transaction committed successfully');
            
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('❌ Service error: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }


    public function handleMultipleAttachments(DocumentComment $comment, array $attachments, User $user): void
    {
        foreach ($attachments as $attachment) {
            try {
                if ($attachment instanceof UploadedFile || 
                    $attachment instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    
                    // Handle Livewire temporary uploaded file
                    if ($attachment instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $uploadedFile = new UploadedFile(
                            $attachment->getRealPath(),
                            $attachment->getClientOriginalName(),
                            $attachment->getMimeType(),
                            null,
                            true
                        );
                    } else {
                        $uploadedFile = $attachment;
                    }
                    
                    $this->validateAttachment($uploadedFile);
                    
                    $originalName = $uploadedFile->getClientOriginalName();
                    $extension = $uploadedFile->getClientOriginalExtension();
                    $filename = Str::uuid() . '.' . $extension;
                    $path = "discussion-attachments/{$comment->document_request_id}/{$comment->id}";
                    
                    // Store file
                    $filePath = $uploadedFile->storeAs($path, $filename, 'public');
                    
                    // Use attachmentFiles relationship instead of attachments
                    $comment->attachmentFiles()->create([
                        'filename' => $filename,
                        'original_filename' => $originalName,
                        'file_path' => $filePath,
                        'mime_type' => $uploadedFile->getMimeType(),
                        'file_size' => $uploadedFile->getSize(),
                        'uploaded_by_nik' => $user->nik,
                        'uploaded_by_name' => $user->name,
                    ]);
                    
                    \Log::info('Attachment uploaded successfully', [
                        'filename' => $originalName,
                        'path' => $filePath,
                        'size' => $uploadedFile->getSize()
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Error handling attachment: ' . $e->getMessage(), [
                    'attachment' => get_class($attachment),
                    'user_nik' => $user->nik,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e; // Re-throw to handle in calling method
            }
        }
    }

    protected function validateAttachment(UploadedFile $file): void
    {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/jpg', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('File type not allowed.');
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \Exception('File size too large. Maximum 10MB.');
        }
    }

    public function closeDiscussion(DocumentRequest $documentRequest, User $user, string $reason = null): DocumentComment {
        if ($user->role !== 'head_legal') {
            throw new \Exception('Only Head Legal can close the discussion.');
        }

        // NEW: Check if finance has participated
        if (!$this->hasFinanceParticipated($documentRequest)) {
            throw new \Exception('Cannot close discussion. Finance team must participate first.');
        }

        if (!$documentRequest->canCloseDiscussion($user)) {
            throw new \Exception('Discussion cannot be closed yet.');
        }

        return DB::transaction(function () use ($documentRequest, $user, $reason) {
            $closeComment = $documentRequest->comments()->create([
                'user_id' => $user->id,
                'user_nik' => $user->nik,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'comment' => $reason ?: 'Discussion forum has been closed by Head Legal.',
                'is_forum_closed' => true,
                'forum_closed_at' => now(),
                'forum_closed_by_nik' => $user->nik,
                'forum_closed_by_name' => $user->name,
            ]);

            $documentRequest->update(['status' => 'agreement_creation']);

            // NEW: Send closure notifications
            $this->sendClosureNotifications($documentRequest, $user, $reason);

            return $closeComment;
        });
    }

    public function getDiscussionStats(DocumentRequest $documentRequest): array
    {
        $comments = $documentRequest->comments();
        
        return [
            'total_comments' => $comments->count(),
            'total_attachments' => DocumentCommentAttachment::whereHas('comment', function($q) use ($documentRequest) {
                $q->where('document_request_id', $documentRequest->id);
            })->count(),
            'participants_count' => $comments->distinct('user_nik')->count(),
            'finance_participated' => $this->hasFinanceParticipated($documentRequest), // UPDATED
            'is_closed' => $documentRequest->isDiscussionClosed(),
            'can_be_closed' => $this->canCloseDiscussion($documentRequest), // UPDATED
            'last_activity' => $comments->latest()->first()?->created_at,
        ];
    }

    // COMPLETE REWRITE: Fix all attachment issues
    public function getDiscussionTimeline(DocumentRequest $documentRequest): array
    {
        \Log::info('Loading discussion timeline for document: ' . $documentRequest->id);

        try {
            // Method 1: Get comments with attachments using direct query
            $comments = DB::table('document_comments as dc')
                ->leftJoin('document_comment_attachments as dca', 'dc.id', '=', 'dca.document_comment_id')
                ->where('dc.document_request_id', $documentRequest->id)
                ->whereNull('dc.parent_id')
                ->whereNull('dc.deleted_at')
                ->select([
                    'dc.*',
                    DB::raw('GROUP_CONCAT(dca.id) as attachment_ids'),
                    DB::raw('GROUP_CONCAT(dca.original_filename) as attachment_names'),
                    DB::raw('GROUP_CONCAT(dca.file_size) as attachment_sizes'),
                    DB::raw('GROUP_CONCAT(dca.mime_type) as attachment_types'),
                    DB::raw('COUNT(dca.id) as attachment_count')
                ])
                ->groupBy('dc.id')
                ->orderBy('dc.created_at', 'asc')
                ->get();

            \Log::info('Found ' . $comments->count() . ' comments via direct query');

            $timeline = [];

            foreach ($comments as $comment) {
                \Log::info('Processing comment ID: ' . $comment->id . ' with ' . $comment->attachment_count . ' attachments');

                // Get replies
                $replies = DB::table('document_comments')
                    ->where('parent_id', $comment->id)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Process attachments for main comment
                $attachments = [];
                if ($comment->attachment_count > 0 && $comment->attachment_ids) {
                    $attachmentIds = explode(',', $comment->attachment_ids);
                    $attachmentNames = explode(',', $comment->attachment_names);
                    $attachmentSizes = explode(',', $comment->attachment_sizes);
                    $attachmentTypes = explode(',', $comment->attachment_types);

                    for ($i = 0; $i < count($attachmentIds); $i++) {
                        if (isset($attachmentIds[$i]) && $attachmentIds[$i]) {
                            $attachments[] = [
                                'id' => (int)$attachmentIds[$i],
                                'name' => $attachmentNames[$i] ?? 'Unknown',
                                'filename' => $attachmentNames[$i] ?? 'Unknown',
                                'size' => $this->formatFileSize((int)($attachmentSizes[$i] ?? 0)),
                                'mime_type' => $attachmentTypes[$i] ?? 'application/octet-stream',
                                'uploaded_by' => $comment->user_name,
                                'download_url' => route('discussion.attachment.download', $attachmentIds[$i]),
                            ];
                        }
                    }
                }

                \Log::info('Formatted ' . count($attachments) . ' attachments for comment ' . $comment->id);

                // Process replies
                $formattedReplies = [];
                foreach ($replies as $reply) {
                    $formattedReplies[] = [
                        'id' => $reply->id,
                        'user' => [
                            'name' => $reply->user_name,
                            'role' => $reply->user_role,
                            'nik' => $reply->user_nik,
                        ],
                        'comment' => $reply->comment,
                        'attachments' => [], // Replies don't have attachments in this version
                        'created_at' => $reply->created_at,
                    ];
                }

                $timelineItem = [
                    'id' => $comment->id,
                    'user' => [
                        'name' => $comment->user_name,
                        'role' => $comment->user_role,
                        'nik' => $comment->user_nik,
                    ],
                    'comment' => $comment->comment,
                    'attachments' => $attachments,
                    'is_resolved' => (bool)$comment->is_resolved,
                    'resolved_by' => null,
                    'resolved_at' => $comment->resolved_at,
                    'created_at' => $comment->created_at,
                    'replies' => $formattedReplies,
                ];

                $timeline[] = $timelineItem;

                \Log::info('Added timeline item for comment ' . $comment->id . ' with ' . count($attachments) . ' attachments');
            }

            \Log::info('Final timeline has ' . count($timeline) . ' items');

            return $timeline;

        } catch (\Exception $e) {
            \Log::error('Error loading discussion timeline: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Fallback: Try simpler approach
            return $this->getDiscussionTimelineFallback($documentRequest);
        }
    }

    // Fallback method if main approach fails
    private function getDiscussionTimelineFallback(DocumentRequest $documentRequest): array
    {
        \Log::info('Using fallback timeline method');

        $comments = DocumentComment::where('document_request_id', $documentRequest->id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        $timeline = [];

        foreach ($comments as $comment) {
            // Get attachments directly from database
            $attachments = DocumentCommentAttachment::where('document_comment_id', $comment->id)->get();
            
            $formattedAttachments = [];
            foreach ($attachments as $attachment) {
                $formattedAttachments[] = [
                    'id' => $attachment->id,
                    'name' => $attachment->original_filename,
                    'filename' => $attachment->filename,
                    'size' => $this->formatFileSize($attachment->file_size),
                    'mime_type' => $attachment->mime_type,
                    'uploaded_by' => $attachment->uploaded_by_name,
                    'download_url' => route('discussion.attachment.download', $attachment->id),
                ];
            }

            // Get replies
            $replies = DocumentComment::where('parent_id', $comment->id)
                ->orderBy('created_at', 'asc')
                ->get();

            $formattedReplies = [];
            foreach ($replies as $reply) {
                $formattedReplies[] = [
                    'id' => $reply->id,
                    'user' => [
                        'name' => $reply->user_name,
                        'role' => $reply->user_role,
                        'nik' => $reply->user_nik,
                    ],
                    'comment' => $reply->comment,
                    'attachments' => [],
                    'created_at' => $reply->created_at,
                ];
            }

            $timeline[] = [
                'id' => $comment->id,
                'user' => [
                    'name' => $comment->user_name,
                    'role' => $comment->user_role,
                    'nik' => $comment->user_nik,
                ],
                'comment' => $comment->comment,
                'attachments' => $formattedAttachments,
                'is_resolved' => $comment->is_resolved,
                'resolved_by' => null,
                'resolved_at' => $comment->resolved_at,
                'created_at' => $comment->created_at,
                'replies' => $formattedReplies,
            ];
        }

        \Log::info('Fallback timeline has ' . count($timeline) . ' items');
        return $timeline;
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    public function downloadAttachment(int $attachmentId, User $user): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $attachment = DocumentCommentAttachment::findOrFail($attachmentId);
        
        if (!$this->canUserAccessAttachment($attachment, $user)) {
            throw new \Exception('You do not have permission to download this file.');
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            throw new \Exception('File not found.');
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    public function deleteAttachment(int $attachmentId, User $user): bool
    {
        $attachment = DocumentCommentAttachment::findOrFail($attachmentId);
        
        if ($attachment->uploaded_by_nik !== $user->nik && $user->role !== 'head_legal') {
            throw new \Exception('You can only delete your own attachments.');
        }

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        return $attachment->delete();
    }

    public function canUserParticipate(User $user): bool
    {
        $allowedRoles = [
            'head_legal',
            'general_manager', 
            'senior_manager',
            'manager',
            'supervisor',
            'head',
            'reviewer_legal',
            'head_finance',
            'finance',
        ];

        return in_array($user->role, $allowedRoles);
    }

    public function canUserAccessDiscussion(DocumentRequest $documentRequest, User $user): bool
    {
        if (!$this->canUserParticipate($user)) {
            return false;
        }

        try {
             // 1. Check if user is the requester
            if ($documentRequest->nik === $user->nik) {
                return true;
            }
            
            // 2. Check if user has approved this document
            if ($documentRequest->approvals()->where('approver_nik', $user->nik)->exists()) {
                return true;
            }
            
            // 3. Check if user has already commented on this document
            if ($documentRequest->comments()->where('user_nik', $user->nik)->exists()) {
                return true;
            }
            
            // 4. NEW: Finance team can access ALL discussions that are open
            if (in_array($user->role, ['finance', 'head_finance']) && in_array($documentRequest->status, ['discussion', 'in_discussion'])) {
                return true;
            }
            
            // 5. Role-based access for privileged users
            $privilegedRoles = ['head_legal', 'reviewer_legal', 'general_manager'];
            if (in_array($user->role, $privilegedRoles)) {
                return true;
            }
            
            // 6. Division-based access for managers/supervisors
            if (in_array($user->role, ['head', 'senior_manager', 'manager', 'supervisor'])) {
                return $documentRequest->divisi === $user->divisi;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::error('Error in canUserAccessDiscussion', [
                'user_nik' => $user->nik,
                'document_id' => $documentRequest->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function canUserAccessAttachment(DocumentCommentAttachment $attachment, User $user): bool
    {
        $documentRequest = $attachment->comment->documentRequest;
        return $this->canUserAccessDiscussion($documentRequest, $user);
    }

    // ========== NEW METHODS - Finance Features ==========

    /**
     * Check if finance team has participated in discussion
     */
    public function hasFinanceParticipated(DocumentRequest $documentRequest): bool
    {
        return $documentRequest->comments()
            ->whereIn('user_role', ['finance', 'head_finance'])
            ->where('is_forum_closed', false) // Exclude closure comments
            ->exists();
    }

    /**
     * Check if discussion can be closed
     */
    public function canCloseDiscussion(DocumentRequest $documentRequest): bool
    {
        return $this->hasFinanceParticipated($documentRequest) && 
               !$documentRequest->isDiscussionClosed();
    }

    /**
     * Send notifications when new comment is added
     */
    protected function sendCommentNotifications(DocumentRequest $documentRequest, User $commenter, DocumentComment $comment): void
    {
        // Get all users who have access to this discussion
        $users = User::whereIn('role', [
            'head_legal', 'general_manager', 'head', 
            'reviewer_legal', 'finance'
        ])->where('nik', '!=', $commenter->nik)->get();

        foreach ($users as $user) {
            if ($this->canUserAccessDiscussion($documentRequest, $user)) {
                try {
                    \Filament\Notifications\Notification::make()
                        ->title('New Comment in Discussion')
                        ->body("New comment by {$commenter->name} in document: {$documentRequest->title}")
                        ->info()
                        ->sendToDatabase($user, true); // Force send with user data
                } catch (\Exception $e) {
                    \Log::error('Failed to send notification', [
                        'user_nik' => $user->nik,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Special notification for finance if they haven't participated yet
        if (!$this->hasFinanceParticipated($documentRequest) && $commenter->role !== 'finance') {
            $financeUsers = User::whereIn('role', ['finance', 'head_finance'])->get();
            foreach ($financeUsers as $financeUser) {
                try {
                    \Filament\Notifications\Notification::make()
                        ->title('Finance Input Required')
                        ->body("Discussion for document '{$documentRequest->title}' requires your participation.")
                        ->warning()
                        ->persistent()
                        ->sendToDatabase($financeUser, true);
                } catch (\Exception $e) {
                    \Log::error('Failed to send finance notification', [
                        'user_nik' => $financeUser->nik,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Send notifications when discussion is closed
     */
    protected function sendClosureNotifications(DocumentRequest $documentRequest, User $closer, string $reason = null): void
    {
        // Notify all participants
        $participantNiks = $documentRequest->comments()->distinct('user_nik')->pluck('user_nik');
        $participants = User::whereIn('nik', $participantNiks)->where('nik', '!=', $closer->nik)->get();

        foreach ($participants as $participant) {
            try {
                \Filament\Notifications\Notification::make()
                    ->title('Discussion Closed')
                    ->body("Discussion for document '{$documentRequest->title}' has been closed by {$closer->name}." . 
                           ($reason ? " Reason: {$reason}" : ""))
                    ->warning()
                    ->sendToDatabase($participant, true);
            } catch (\Exception $e) {
                \Log::error('Failed to send closure notification', [
                    'user_nik' => $participant->nik,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Mark user as having seen comments
     */
    public function markUserSeenComments(User $user): void
    {
        $user->update(['last_seen_comments_at' => now()]);
    }

    /**
     * Get count of unread discussions for user
     */
    public function getUnreadDiscussionsCount(User $user): int
    {
        return DocumentRequest::where('status', 'discussion')
            ->whereHas('comments', function ($query) use ($user) {
                $query->where('created_at', '>', $user->last_seen_comments_at ?? now()->subDays(30))
                      ->where('user_nik', '!=', $user->nik);
            })
            ->get()
            ->filter(function ($document) use ($user) {
                return $this->canUserAccessDiscussion($document, $user);
            })
            ->count();
    }

    /**
     * Send reminder to finance team
     */
    public function remindFinanceTeam(DocumentRequest $documentRequest, User $requester): void
    {
        if ($requester->role !== 'head_legal') {
            throw new \Exception('Only Head Legal can send finance reminders.');
        }

        if ($this->hasFinanceParticipated($documentRequest)) {
            throw new \Exception('Finance team has already participated in this discussion.');
        }

        $financeUsers = User::where('role', 'finance')->get();
        
        foreach ($financeUsers as $financeUser) {
            \Filament\Notifications\Notification::make()
                ->title('Finance Input Required - Reminder')
                ->body("REMINDER: Discussion for document '{$documentRequest->title}' requires your participation. Please review and provide your input.")
                ->warning()
                ->persistent()
                ->sendToDatabase($financeUser);
        }

        // Log the reminder
        $documentRequest->comments()->create([
            'user_id' => $requester->id,
            'user_nik' => $requester->nik,
            'user_name' => $requester->name,
            'user_role' => $requester->role,
            'comment' => 'Finance team has been reminded to participate in this discussion.',
            'is_forum_closed' => false,
        ]);
    }

    /**
     * Get discussion participants - MISSING METHOD
     */
    public function getDiscussionParticipants(DocumentRequest $documentRequest): array
    {
        $participants = $documentRequest->comments()
            ->select('user_nik', 'user_name', 'user_role')
            ->distinct()
            ->where('is_forum_closed', false)
            ->get()
            ->map(function ($comment) {
                return [
                    'nik' => $comment->user_nik,
                    'name' => $comment->user_name,
                    'role' => $comment->user_role,
                    'role_color' => $this->getRoleColor($comment->user_role),
                ];
            })
            ->unique('nik')
            ->values()
            ->toArray();

        return $participants;
    }

    /**
     * Get role color for display
     */
    private function getRoleColor(string $role): string
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

    /**
     * Check if discussion is closed
     */
    public function isDiscussionClosed(DocumentRequest $documentRequest): bool
    {
        return $documentRequest->comments()
            ->where('is_forum_closed', true)
            ->exists();
    }/**
     * NEW: Get all discussions that need finance input
     */
    public function getDiscussionsNeedingFinanceInput(User $financeUser): array
    {
        if (!in_array($financeUser->role, ['finance', 'head_finance'])) {
            return [];
        }

        // Get all discussions that are open and finance hasn't participated yet
        $discussions = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])
            ->whereDoesntHave('comments', function($query) {
                $query->where('user_role', 'finance')
                      ->where('is_forum_closed', false);
            })
            ->with(['user', 'comments' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $discussions->map(function($doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'requester_name' => $doc->nama,
                'requester_divisi' => $doc->divisi,
                'total_comments' => $doc->comments()->where('is_forum_closed', false)->count(),
                'last_activity' => $doc->comments()->latest()->first()?->created_at,
                'created_at' => $doc->created_at,
                'priority' => $this->calculateDiscussionPriority($doc),
            ];
        })->toArray();
    }

    /**
     * Calculate discussion priority based on age and activity
     */
    private function calculateDiscussionPriority(DocumentRequest $doc): string
    {
        $daysSinceCreated = now()->diffInDays($doc->created_at);
        $commentsCount = $doc->comments()->where('is_forum_closed', false)->count();
        
        if ($daysSinceCreated > 7 || $commentsCount > 10) {
            return 'high';
        } elseif ($daysSinceCreated > 3 || $commentsCount > 5) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * NEW: Get finance dashboard stats
     */
    public function getFinanceDashboardStats(User $financeUser): array
    {
        if (!in_array($financeUser->role, ['finance', 'head_finance'])) {
            return [];
        }

        $totalDiscussions = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])->count();
        
        $needsFinanceInput = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])
            ->whereDoesntHave('comments', function($query) {
                $query->where('user_role', 'finance')
                      ->where('is_forum_closed', false);
            })->count();

        $financeParticipated = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])
            ->whereHas('comments', function($query) {
                $query->where('user_role', 'finance')
                      ->where('is_forum_closed', false);
            })->count();

        $myComments = DocumentComment::where('user_nik', $financeUser->nik)
            ->where('is_forum_closed', false)
            ->count();

        return [
            'total_discussions' => $totalDiscussions,
            'needs_finance_input' => $needsFinanceInput,
            'finance_participated' => $financeParticipated,
            'my_comments' => $myComments,
            'pending_urgent' => $this->getUrgentDiscussionsForFinance()->count(),
        ];
    }

    /**
     * Get urgent discussions that need finance input
     */
    public function getUrgentDiscussionsForFinance(): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])
            ->whereDoesntHave('comments', function($query) {
                $query->whereIn('user_role', ['finance', 'head_finance'])
                      ->where('is_forum_closed', false);
            })
            ->where('created_at', '<=', now()->subDays(7)) // Older than 7 days
            ->with(['user'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Send notification to finance when discussion opens
     */
    public function notifyFinanceOnDiscussionOpen(DocumentRequest $documentRequest): void
    {
       $financeUsers = User::whereIn('role', ['finance', 'head_finance'])->get();
        
        foreach ($financeUsers as $financeUser) {
            try {
                \Filament\Notifications\Notification::make()
                    ->title('New Discussion Opened')
                    ->body("New discussion opened for document: {$documentRequest->title}. Your input may be required.")
                    ->info()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Discussion')
                            ->url(route('discussion.show', $documentRequest->id))
                    ])
                    ->sendToDatabase($financeUser, true);
            } catch (\Exception $e) {
                \Log::error('Failed to send finance notification', [
                    'user_nik' => $financeUser->nik,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
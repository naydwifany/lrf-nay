{{-- resources/views/filament/components/chat-message.blade.php --}}
@php
    $isOwnMessage = $comment->user_nik === auth()->user()->nik;
    $isSystemMessage = $comment->user_role === 'system';
    $isForumClosed = $comment->is_forum_closed;
    $depth = $level ?? 0;
    $maxDepth = 3; // Maximum nesting level
@endphp

<div class="message-thread {{ $depth > 0 ? 'ml-' . min($depth * 8, $maxDepth * 8) : '' }}">
    <!-- Main Message -->
    <div class="flex space-x-3 group {{ $isForumClosed ? 'bg-red-50 rounded-lg p-3 border border-red-200' : '' }}">
        <!-- Avatar -->
        <div class="flex-shrink-0">
            @if($isSystemMessage)
                <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            @else
                <div class="w-8 h-8 {{ $isOwnMessage ? 'bg-blue-600' : 'bg-gray-600' }} rounded-full flex items-center justify-center text-white text-sm font-medium">
                    {{ strtoupper(substr($comment->user_name, 0, 2)) }}
                </div>
            @endif
        </div>

        <!-- Message Content -->
        <div class="flex-1 min-w-0">
            <!-- Message Header -->
            <div class="flex items-center space-x-2 mb-1">
                <span class="font-medium text-gray-900 text-sm">{{ $comment->user_name }}</span>
                
                @if(!$isSystemMessage)
                    <span class="px-2 py-0.5 bg-{{ $this->getRoleColor($comment->user_role) }}-100 text-{{ $this->getRoleColor($comment->user_role) }}-800 rounded-full text-xs font-medium">
                        {{ ucfirst(str_replace('_', ' ', $comment->user_role)) }}
                    </span>
                @endif

                <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>

                @if($isForumClosed)
                    <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                        🔒 Forum Closed
                    </span>
                @endif
            </div>

            <!-- Message Body -->
            <div class="text-sm text-gray-900 mb-2 {{ $isSystemMessage ? 'bg-blue-50 border border-blue-200 rounded p-3 italic' : '' }}">
                {!! nl2br(e($comment->comment)) !!}
            </div>

            <!-- Attachments -->
            @if($comment->attachments && $comment->attachments->count() > 0)
                <div class="mb-3 space-y-2">
                    @foreach($comment->attachments as $attachment)
                        <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded border max-w-xs">
                            <x-filament::icon 
                                :icon="$attachment->getFileIcon()" 
                                class="w-4 h-4 text-gray-500 flex-shrink-0"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-900 truncate">
                                    {{ $attachment->original_filename }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $attachment->getFormattedFileSize() }}
                                </div>
                            </div>
                            <div class="flex space-x-1">
                                <a 
                                    href="{{ route('discussion.attachment.download', $attachment->id) }}"
                                    class="text-blue-600 hover:text-blue-800 text-xs p-1"
                                    title="Download"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"></path>
                                    </svg>
                                </a>
                                
                                @if($attachment->canPreview())
                                    <button 
                                        wire:click="previewFile({{ $attachment->id }})"
                                        class="text-gray-600 hover:text-gray-800 text-xs p-1"
                                        title="Preview"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Message Actions -->
            @if(!$isSystemMessage && !$isForumClosed && app(\App\Services\DocumentDiscussionService::class)->canUserParticipate(auth()->user()))
                <div class="flex items-center space-x-4 opacity-0 group-hover:opacity-100 transition-opacity">
                    <!-- Reply Button -->
                    <button 
                        wire:click="replyTo({{ $comment->id }})"
                        class="flex items-center space-x-1 text-xs text-gray-500 hover:text-blue-600 transition-colors"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        <span>Reply</span>
                    </button>

                    <!-- React Button -->
                    <div class="flex items-center space-x-1">
                        <button 
                            wire:click="addReaction({{ $comment->id }}, '👍')"
                            class="text-xs text-gray-500 hover:text-blue-600 transition-colors"
                            title="Like"
                        >
                            👍
                        </button>
                        <button 
                            wire:click="addReaction({{ $comment->id }}, '❤️')"
                            class="text-xs text-gray-500 hover:text-red-600 transition-colors"
                            title="Love"
                        >
                            ❤️
                        </button>
                        <button 
                            wire:click="addReaction({{ $comment->id }}, '✅')"
                            class="text-xs text-gray-500 hover:text-green-600 transition-colors"
                            title="Approve"
                        >
                            ✅
                        </button>
                    </div>

                    <!-- Timestamp (exact) -->
                    <span class="text-xs text-gray-400" title="{{ $comment->created_at->format('Y-m-d H:i:s') }}">
                        {{ $comment->created_at->format('H:i') }}
                    </span>

                    <!-- Edit/Delete (own messages) -->
                    @if($isOwnMessage)
                        <div class="flex items-center space-x-2">
                            <button 
                                wire:click="editComment({{ $comment->id }})"
                                class="text-xs text-gray-500 hover:text-blue-600 transition-colors"
                                title="Edit"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Quick Reply Form (if replying to this message) -->
            @if($this->replyingTo === $comment->id)
                <div class="mt-3 ml-6">
                    <form wire:submit="submitReply" class="bg-gray-50 rounded-lg p-3 border">
                        <div class="flex space-x-2">
                            <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <div class="flex-1">
                                <textarea 
                                    wire:model="replyContent" 
                                    rows="2" 
                                    class="w-full border-gray-300 rounded text-sm resize-none focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Write a reply..."
                                    required
                                ></textarea>
                                <div class="flex items-center justify-between mt-2">
                                    <label class="flex items-center space-x-1 cursor-pointer text-gray-500 hover:text-gray-700 text-xs">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        <span>Attach</span>
                                        <input 
                                            type="file" 
                                            wire:model="replyAttachments" 
                                            multiple
                                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                            class="hidden"
                                        >
                                    </label>
                                    
                                    <div class="flex space-x-2">
                                        <button 
                                            type="button" 
                                            wire:click="cancelReply"
                                            class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1"
                                        >
                                            Cancel
                                        </button>
                                        <button 
                                            type="submit" 
                                            class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 transition-colors"
                                            wire:loading.attr="disabled"
                                        >
                                            <span wire:loading.remove>Reply</span>
                                            <span wire:loading>Sending...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <!-- Nested Replies -->
    @if($comment->replies && $comment->replies->count() > 0 && $depth < $maxDepth)
        <div class="mt-3 space-y-3">
            @foreach($comment->replies as $reply)
                @include('filament.components.chat-message', ['comment' => $reply, 'level' => $depth + 1])
            @endforeach
        </div>
    @elseif($comment->replies && $comment->replies->count() > 0 && $depth >= $maxDepth)
        <!-- Show "View more replies" for deep nesting -->
        <div class="mt-2 ml-8">
            <button 
                wire:click="showMoreReplies({{ $comment->id }})"
                class="text-xs text-blue-600 hover:text-blue-800 flex items-center space-x-1"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                <span>View {{ $comment->replies->count() }} more replies</span>
            </button>
        </div>
    @endif
</div>
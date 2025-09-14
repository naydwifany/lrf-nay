{{-- resources/views/filament/admin/resources/document-request-resource/pages/view-discussion.blade.php --}}
{{-- FIXED VERSION - PROPER DARK/LIGHT MODE STYLING --}}

<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Document Header - Compact --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2"></h2>
            <div class="text-sm text-gray-600 dark:text-gray-400 flex flex-wrap gap-4">
                <span class="flex items-center gap-1">
                    <span class="text-blue-500">📄</span> 
                    <span class="font-medium">ID:</span> {{ $record->id }}
                </span>
                <span class="flex items-center gap-1">
                    <span class="text-green-500">👤</span> 
                    {{ $record->nama }}
                </span>
                <span class="flex items-center gap-1">
                    <span class="text-purple-500">🏢</span> 
                    {{ $record->divisi }}
                </span>
                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                    {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                </span>
            </div>
        </div>

        {{-- Stats - Horizontal Cards --}}
        <div class="flex flex-wrap gap-3">
            <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $discussionStats['total_comments'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Comments</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $discussionStats['participants_count'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">People</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $discussionStats['total_attachments'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Files</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center">
                <div class="text-2xl font-bold {{ ($discussionStats['finance_participated'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $discussionStats['finance_participated'] ? 'Already' : 'Not yet' }}
                </div> 
                <div class="text-sm text-gray-600 dark:text-gray-400">Finance Involved</div>
            </div>
        </div>

        {{-- Comment Form - Fixed Styling --}}
        @if($canParticipate && !$isDiscussionClosed)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 class="text-base font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                <span class="text-blue-500">💬</span> Add Comment
            </h3>
            
            <form wire:submit.prevent="addComment" class="space-y-4">
                <div>
                    <textarea 
                        wire:model.defer="newComment" 
                        rows="4" 
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:focus:border-blue-400 dark:focus:ring-blue-400 transition-colors duration-200"
                        placeholder="Type your comment here..."
                        required
                    ></textarea>
                    @error('newComment')
                        <div class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <input 
                            type="file" 
                            wire:model.defer="newCommentAttachments" 
                            multiple 
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt"
                            class="hidden"
                            id="file-upload"
                        >
                        <label for="file-upload" class="cursor-pointer inline-flex items-center gap-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 transition-colors duration-200">
                            <span class="text-orange-500">📎</span> Attach Files
                        </label>
                        
                        {{-- Show selected files --}}
                        @if(!empty($newCommentAttachments))
                        <div class="text-sm text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-full">
                            {{ count($newCommentAttachments) }} file(s) selected
                        </div>
                        @endif
                    </div>
                    
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm rounded-lg font-medium disabled:opacity-50 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
                    >
                        <span wire:loading.remove wire:target="addComment">
                            <span class="text-white">🚀</span> Post Comment
                        </span>
                        <span wire:loading wire:target="addComment" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Posting...
                        </span>
                    </button>
                </div>
            </form>
        </div>
        @else
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <div class="text-sm font-medium text-amber-800 dark:text-amber-200">Cannot add comments</div>
                    <div class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                        @if(!$canParticipate)
                            Your role ({{ auth()->user()->role }}) is not authorized to participate.
                        @elseif($isDiscussionClosed)
                            This discussion has been closed.
                        @else
                            Discussion is not available.
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Comments Section - Fixed Styling --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-blue-500">💬</span> Discussion Timeline ({{ count($discussionTimeline) }})
                </h3>
            </div>
            
            <div class="p-6 space-y-6">
                @if(count($discussionTimeline) > 0)
                    @foreach($discussionTimeline as $comment)
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden bg-white dark:bg-gray-800 shadow-sm">
                        {{-- Comment Header --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 text-white text-sm flex items-center justify-center font-medium shadow-lg flex-shrink-0">
                                    {{ substr($comment['user']['name'], 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $comment['user']['name'] }}</div>
                                        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium whitespace-nowrap">
                                            {{ ucfirst(str_replace('_', ' ', $comment['user']['role'])) }}
                                        </span>
                                        @if($comment['is_resolved'] ?? false)
                                        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200 rounded-full text-xs font-medium whitespace-nowrap">✓ Resolved</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Comment Content --}}
                        <div class="p-4">
                            @if(!empty($comment['comment']))
                            <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                {!! nl2br(e($comment['comment'])) !!}
                            </div>
                            @endif

                        {{-- Attachments - Fixed Download Links --}}
                        @if(is_array($comment['attachments']) && count($comment['attachments']) > 0)
                        <div class="mb-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3 flex items-center gap-2">
                                <span class="text-orange-500">📎</span> Attachments ({{ count($comment['attachments']) }})
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($comment['attachments'] as $attachment)
                                <a href="{{ asset('storage/' . ($attachment['file_path'] ?? 'discussion-attachments/' . ($attachment['filename'] ?? $attachment['stored_name'] ?? $attachment['name']))) }}" 
                                   download="{{ $attachment['name'] ?? $attachment['original_filename'] ?? 'attachment' }}"
                                   class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 group shadow-sm"
                                   title="Download {{ $attachment['name'] ?? $attachment['original_filename'] ?? 'attachment' }}">
                                    <div class="flex-shrink-0 w-10 h-10 bg-orange-100 dark:bg-orange-900/50 rounded-lg flex items-center justify-center mr-3">
                                        @php
                                            $filename = $attachment['name'] ?? $attachment['original_filename'] ?? 'file';
                                            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                            $icon = match($extension) {
                                                'pdf' => '📄',
                                                'doc', 'docx' => '📝',
                                                'xls', 'xlsx' => '📊',
                                                'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                                'txt' => '📋',
                                                default => '📎'
                                            };
                                        @endphp
                                        <span class="text-base">{{ $icon }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                            {{ $attachment['name'] ?? $attachment['original_filename'] ?? 'Unknown file' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $attachment['size'] ?? ($attachment['file_size'] ? number_format($attachment['file_size'] / 1024, 1) . ' KB' : 'Unknown size') }}
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 group-hover:text-blue-600 dark:group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Reply Button --}}
                        @if($canParticipate && !$isDiscussionClosed)
                        <div class="flex items-center gap-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <button 
                                wire:click="showReplyForm({{ $comment['id'] }})"
                                class="inline-flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors duration-200 py-2 px-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                </svg>
                                <span>Reply</span>
                            </button>
                        </div>
                        @endif
                        </div>

                        {{-- Reply Form --}}
                        @if($canParticipate && !$isDiscussionClosed && isset($showReplyForm[$comment['id']]) && $showReplyForm[$comment['id']])
                        <div class="border-t border-gray-200 dark:border-gray-600 bg-blue-50 dark:bg-blue-900/20">
                            <div class="p-4">
                                <form wire:submit.prevent="replyToComment({{ $comment['id'] }})" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            <span class="text-blue-500">💭</span> Reply to {{ $comment['user']['name'] }}
                                        </label>
                                        <textarea 
                                            wire:model.defer="replyComment"
                                            rows="3"
                                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Type your reply..."
                                            required
                                        ></textarea>
                                    </div>
                                    <div class="flex gap-3">
                                        <button 
                                            type="button"
                                            wire:click="hideReplyForm({{ $comment['id'] }})"
                                            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm rounded-lg font-medium shadow hover:shadow-lg transition-all duration-200"
                                        >
                                            ❌ Cancel
                                        </button>
                                        <button 
                                            type="submit"
                                            wire:loading.attr="disabled"
                                            class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm rounded-lg font-medium shadow-lg hover:shadow-xl disabled:opacity-50 transition-all duration-200"
                                        >
                                            <span wire:loading.remove wire:target="replyToComment">🚀 Post Reply</span>
                                            <span wire:loading wire:target="replyToComment">⏳ Posting...</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif

                        {{-- Replies --}}
                        @if(is_array($comment['replies']) && count($comment['replies']) > 0)
                        <div class="border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                            <div class="p-4">
                                <div class="space-y-3">
                                    @foreach($comment['replies'] as $reply)
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600 ml-6 relative">
                                        <div class="absolute -left-6 top-6 w-4 h-px bg-blue-300 dark:bg-blue-600"></div>
                                        <div class="absolute -left-6 top-6 w-px h-4 bg-blue-300 dark:bg-blue-600"></div>
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-green-500 to-emerald-600 text-white text-xs flex items-center justify-center font-medium">
                                                {{ substr($reply['user']['name'], 0, 1) }}
                                            </div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $reply['user']['name'] }}</span>
                                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded-full text-xs font-medium">
                                                    {{ ucfirst(str_replace('_', ' ', $reply['user']['role'])) }}
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ \Carbon\Carbon::parse($reply['created_at'])->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            {!! nl2br(e($reply['comment'])) !!}
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @else
                    <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                        <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                            <span class="text-3xl text-white">💬</span>
                        </div>
                        <div class="text-lg font-medium mb-2">No comments yet</div>
                        <div class="text-sm">Be the first to start the discussion!</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Head Legal Notice --}}
        @if($record->status === 'in_discussion' && auth()->user()->role === 'head_legal')
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/50 rounded-lg flex items-center justify-center">
                    <span class="text-xl text-amber-600 dark:text-amber-400">⚖️</span>
                </div>
                <div>
                    <div class="text-base font-semibold text-amber-800 dark:text-amber-200 mb-1">Head Legal Notice</div>
                    <div class="text-sm text-amber-700 dark:text-amber-300">
                        @if(!($discussionStats['finance_participated'] ?? false))
                            ⚠️ Finance team must participate before you can close this discussion.
                        @else
                            ✅ Discussion is ready to be closed. Use the "Close Discussion" button in the header.
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Debug Section - Improved Styling --}}
        @if(config('app.debug') && count($discussionTimeline) > 0)
        <details class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
            <summary class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 cursor-pointer flex items-center gap-2">
                <span class="text-yellow-600">🐛</span> Debug: Attachment Structure
            </summary>
            <div class="mt-4 text-xs text-yellow-700 dark:text-yellow-300 space-y-2">
                @foreach($discussionTimeline as $comment)
                    @if(is_array($comment['attachments']) && count($comment['attachments']) > 0)
                        <div class="bg-yellow-100 dark:bg-yellow-800/30 p-3 rounded border border-yellow-200 dark:border-yellow-600">
                            <div class="font-semibold mb-2">Comment {{ $comment['id'] ?? 'Unknown' }}:</div>
                            @foreach($comment['attachments'] as $attachment)
                                <div class="ml-4 font-mono text-xs bg-yellow-200 dark:bg-yellow-700/50 p-2 rounded mb-1">
                                    {{ json_encode($attachment, JSON_PRETTY_PRINT) }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </details>
        @endif
    </div>

    {{-- Enhanced Loading Indicator --}}
    <div wire:loading class="fixed top-4 right-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg shadow-xl z-50">
        <div class="flex items-center gap-3">
            <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span class="text-sm font-medium">Processing...</span>
        </div>
    </div>

    {{-- Custom Styles for improved dark mode --}}
    <style>
        /* Ensure proper contrast and visibility */
        .dark {
            color-scheme: dark;
        }
        
        /* Better background colors for dark mode */
        .dark .bg-gray-750 {
            background-color: rgb(55 65 81 / 0.8);
        }
        
        /* Force proper text colors */
        .dark .text-gray-900 {
            color: rgb(243 244 246) !important;
        }
        
        .dark .text-gray-700 {
            color: rgb(209 213 219) !important;
        }
        
        .dark .text-gray-600 {
            color: rgb(156 163 175) !important;
        }
        
        /* Ensure proper placeholder colors */
        .dark textarea::placeholder,
        .dark input::placeholder {
            color: rgb(156 163 175) !important;
            opacity: 1;
        }
        
        /* Better focus styles for dark mode */
        .dark textarea:focus,
        .dark input:focus {
            background-color: rgb(55 65 81) !important;
            border-color: rgb(96 165 250) !important;
            box-shadow: 0 0 0 1px rgb(96 165 250) !important;
            color: rgb(243 244 246) !important;
        }
        
        /* Ensure borders are visible in dark mode */
        .dark .border-gray-200 {
            border-color: rgb(75 85 99) !important;
        }
        
        .dark .border-gray-300 {
            border-color: rgb(75 85 99) !important;
        }
        
        /* Better background for form elements in dark mode */
        .dark .bg-gray-50 {
            background-color: rgb(55 65 81) !important;
        }
        
        .dark .bg-white {
            background-color: rgb(31 41 55) !important;
        }
        
        /* Smooth transitions for all elements */
        * {
            transition-property: color, background-color, border-color, opacity;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 200ms;
        }
        
        /* Fix any remaining visibility issues */
        .dark [class*="text-gray-"] {
            opacity: 1 !important;
        }
        
        /* Ensure all text is readable */
        .dark .bg-gray-800 {
            color: rgb(243 244 246);
        }
        
        .dark .bg-gray-700 {
            color: rgb(229 231 235);
        }
        
        /* Better contrast for interactive elements */
        .dark button:not(.bg-gradient-to-r) {
            background-color: rgb(55 65 81);
            color: rgb(243 244 246);
            border-color: rgb(75 85 99);
        }
        
        .dark button:not(.bg-gradient-to-r):hover {
            background-color: rgb(75 85 99);
        }
        
        /* Ensure attachment cards are visible */
        .dark .group {
            background-color: rgb(55 65 81) !important;
            border-color: rgb(75 85 99) !important;
        }
        
        .dark .group:hover {
            background-color: rgb(75 85 99) !important;
        }
    </style>
</x-filament-panels::page>
{{-- resources/views/filament/user/resources/discussion/pages/view-discussion.blade.php --}}
{{-- USER PANEL DISCUSSION VIEW --}}

<x-filament::page>
    <div class="space-y-6">
        
        {{-- Document Information --}}
        <x-filament::section>
            <x-slot name="heading">Document Information</x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Document Number:</strong> 
                        <span class="text-gray-700 dark:text-gray-300">{{ $record->nomor_dokumen ?? 'Not assigned' }}</span>
                    </x-filament::section.description>
                </div>
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Title:</strong> 
                        <span class="text-gray-700 dark:text-gray-300">{{ $record->title }}</span>
                    </x-filament::section.description>
                </div>
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Requester:</strong> 
                        <span class="text-gray-700 dark:text-gray-300">{{ $record->nama }}</span>
                    </x-filament::section.description>
                </div>
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Status:</strong> 
                        <x-filament::badge color="info">{{ $record->status }}</x-filament::badge>
                    </x-filament::section.description>
                </div>
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Document Type:</strong> 
                        <span class="text-gray-700 dark:text-gray-300">{{ $record->doctype->document_name ?? 'No Type' }}</span>
                    </x-filament::section.description>
                </div>
                <div>
                    <x-filament::section.description>
                        <strong class="text-gray-900 dark:text-gray-100">Division:</strong> 
                        <span class="text-gray-700 dark:text-gray-300">{{ $record->divisi }}</span>
                    </x-filament::section.description>
                </div>
            </div>
        </x-filament::section>

        {{-- Discussion Statistics --}}
        @if(isset($discussionStats))
        <x-filament::section>
            <x-slot name="heading">Discussion Statistics</x-slot>
            
            <div class="flex flex-wrap gap-3">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center shadow-sm dark:shadow-gray-900/20">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $discussionStats['total_comments'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Comments</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center shadow-sm dark:shadow-gray-900/20">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $discussionStats['total_attachments'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Files</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center shadow-sm dark:shadow-gray-900/20">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ count($participants ?? []) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Participants</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center shadow-sm dark:shadow-gray-900/20">
                    <div class="text-2xl font-bold {{ ($discussionStats['finance_participated'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ ($discussionStats['finance_participated'] ?? false) ? '✓' : '✗' }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Finance</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex-1 min-w-[120px] text-center shadow-sm dark:shadow-gray-900/20">
                    <div class="text-2xl font-bold {{ ($discussionStats['is_closed'] ?? false) ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ ($discussionStats['is_closed'] ?? false) ? 'CLOSED' : 'OPEN' }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Status</div>
                </div>
            </div>
        </x-filament::section>
        @endif

        {{-- Participants --}}
        @if(!empty($participants))
        <x-filament::section>
            <x-slot name="heading">Participants</x-slot>
            
            <div class="flex flex-wrap gap-2">
                @foreach($participants as $participant)
                    <x-filament::badge :color="$participant['role_color'] ?? 'gray'">
                        {{ $participant['name'] }} ({{ $participant['role'] }})
                    </x-filament::badge>
                @endforeach
            </div>
        </x-filament::section>
        @endif

        {{-- Discussion Thread --}}
        <x-filament::section>
            <x-slot name="heading">Discussion Thread</x-slot>
            
            {{-- Add Comment Form --}}
            @if($canParticipate ?? true)
            <div class="mb-6 p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm dark:shadow-gray-900/10">
                <form wire:submit="addComment">
                    <div class="space-y-4">
                        <div>
                            <textarea 
                                wire:model.defer="newComment" 
                                rows="4" 
                                class="w-full text-sm rounded-xl border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-200"
                                placeholder="Type your comment here..."
                                required
                            ></textarea>
                            @error('newComment') 
                                <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <div class="flex justify-between items-center">
                           <div class="flex items-center gap-3">
                                <input 
                                    type="file" 
                                    wire:model="attachments" 
                                    multiple 
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt"
                                    class="hidden"
                                    id="file-upload"
                                >
                                <label for="file-upload" class="cursor-pointer inline-flex items-center gap-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600 transition-all duration-200 hover:shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                    </svg>
                                    Attach Files
                                </label>
                                
                                {{-- Show selected files --}}
                                @if(!empty($attachments))
                                <div class="text-sm text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-full">
                                    {{ count($attachments) }} file(s) selected
                                </div>
                                @endif
                            </div>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" size="md">
                                Post Comment
                            </x-filament::button>
                        </div>
                    </div>
                </form>
            </div>
            @else
            <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">You don't have permission to participate in this discussion.</p>
            </div>
            @endif

            {{-- Comments List --}}
            <div class="space-y-6">
                @forelse($this->getComments()->sortByDesc('created_at') as $comment)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm dark:shadow-gray-900/10 transition-shadow duration-200 hover:shadow-md dark:hover:shadow-gray-900/20">
                        {{-- Comment Header --}}
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 text-white text-sm flex items-center justify-center font-semibold shadow-sm ring-2 ring-white dark:ring-gray-800">
                                    {{ substr($comment->user_name, 0, 1) }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $comment->user_name }}</span>
                                        <x-filament::badge :color="$this->getRoleColor($comment->user_role)" size="sm">
                                            {{ ucfirst(str_replace('_', ' ', $comment->user_role)) }}
                                        </x-filament::badge>
                                        @if($comment->is_forum_closed)
                                        <x-filament::badge color="danger" icon="heroicon-o-lock-closed" size="sm">Forum Closed</x-filament::badge>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $comment->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Comment Content --}}
                        @if(!empty($comment->comment))
                        <div class="text-sm text-gray-700 dark:text-gray-300 mb-4 p-4 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700/50 leading-relaxed">
                            {!! nl2br(e($comment->comment)) !!}
                        </div>
                        @endif

                        {{-- Attachments - menggunakan attachmentFiles relationship --}}
                        @php
                            $attachments = $comment->attachmentFiles ?? collect();
                            $hasAttachments = $attachments && $attachments->count() > 0;
                        @endphp
                        
                        @if($hasAttachments)
                        <div class="mb-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <span>Attachments ({{ $attachments->count() }})</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($attachments as $attachment)
                                <a href="{{ asset('storage/' . $attachment->file_path) }}" 
                                   download="{{ $attachment->original_filename }}"
                                   class="flex items-center p-4 bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-all duration-200 group hover:shadow-sm dark:hover:shadow-gray-900/20"
                                   title="Download {{ $attachment->original_filename }}">
                                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-orange-100 to-orange-200 dark:from-orange-900/50 dark:to-orange-800/50 rounded-lg flex items-center justify-center mr-3 shadow-sm">
                                        @php
                                            $extension = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));
                                            $icon = match($extension) {
                                                'pdf' => '📄',
                                                'doc', 'docx' => '📝',
                                                'xls', 'xlsx' => '📊',
                                                'ppt', 'pptx' => '📺',
                                                'jpg', 'jpeg', 'png', 'gif' => '🖼️',
                                                'zip', 'rar' => '📦',
                                                default => '📁'
                                            };
                                        @endphp
                                        <span class="text-xl">{{ $icon }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                                            {{ $attachment->original_filename }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $attachment->getFormattedFileSize() }}
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ml-3">
                                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Reply Button --}}
                        @if($canParticipate ?? true)
                        <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <button 
                                wire:click="replyTo({{ $comment->id }})"
                                class="inline-flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors duration-200 py-2 px-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                </svg>
                                <span>Reply</span>
                            </button>
                        </div>
                        @endif

                        {{-- Replies --}}
                        @if($comment->replies && $comment->replies->count() > 0)
                            <div class="mt-6 ml-8 space-y-4 pl-6 border-l-2 border-blue-200 dark:border-blue-700/50">
                                @foreach($comment->replies as $reply)
                                    <div class="bg-gray-50 dark:bg-gray-900/40 p-4 rounded-lg border border-gray-200 dark:border-gray-700/50">
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-green-500 to-emerald-600 dark:from-green-400 dark:to-emerald-500 text-white text-xs flex items-center justify-center font-semibold shadow-sm">
                                                {{ substr($reply->user_name, 0, 1) }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $reply->user_name }}</span>
                                                <x-filament::badge :color="$this->getRoleColor($reply->user_role)" size="sm">
                                                    {{ ucfirst(str_replace('_', ' ', $reply->user_role)) }}
                                                </x-filament::badge>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $reply->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300 pl-11 leading-relaxed">
                                            {!! nl2br(e($reply->comment)) !!}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm dark:shadow-gray-900/10">
                        <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-r from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-full flex items-center justify-center shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No comments yet</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Be the first to start the discussion!</div>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Reply Form Modal --}}
        @if($replyingTo)
        <div class="fixed inset-0 bg-black/60 dark:bg-black/80 flex items-center justify-center z-50 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-lg w-full mx-4 shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Reply to Comment</h3>
                </div>
                
                <form wire:submit="submitReply">
                    <div class="space-y-4">
                        <div>
                            <textarea
                                wire:model.defer="replyContent"
                                rows="4"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 placeholder-gray-500 dark:placeholder-gray-400 transition-colors duration-200"
                                placeholder="Write your reply..."
                                required
                            ></textarea>
                            @error('replyContent') 
                                <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> 
                            @enderror
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <x-filament::button color="gray" wire:click="cancelReply" size="sm">
                                Cancel
                            </x-filament::button>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" size="sm">
                                Post Reply
                            </x-filament::button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</x-filament::page>
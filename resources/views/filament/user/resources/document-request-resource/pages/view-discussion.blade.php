<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Document Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">{{ $record->nomor_dokumen }} - {{ $record->title }}</h2>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <span class="text-sm text-gray-500">Tipe Dokumen:</span>
                    <p class="font-medium">{{ $record->doctype->document_name ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Pemohon:</span>
                    <p class="font-medium">{{ $record->nama }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Status:</span>
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full 
                        {{ $record->isDiscussionClosed() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                        {{ $record->isDiscussionClosed() ? 'Forum Ditutup' : 'Forum Aktif' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium">Diskusi Forum</h3>
            </div>

            <div class="p-6">
                <!-- Add Comment Form -->
                @if(!$record->isDiscussionClosed())
                    <div class="mb-6">
                        <form wire:submit.prevent="addComment">
                            <textarea 
                                wire:model="newComment" 
                                rows="3" 
                                class="w-full border rounded-md p-3"
                                placeholder="Tulis komentar Anda..."></textarea>
                            <button 
                                type="submit" 
                                class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Kirim Komentar
                            </button>
                        </form>
                    </div>
                @endif

                <!-- Comments List -->
                <div class="space-y-4">
                    @forelse($record->comments->where('parent_id', null)->sortBy('created_at') as $comment)
                        <div class="border-l-4 border-blue-200 pl-4">
                            <!-- Main Comment -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-medium">{{ $comment->user->name ?? 'Unknown' }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ ucfirst(str_replace('_', ' ', $comment->user_role)) }} • 
                                            {{ $comment->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                                <p class="text-gray-700">{{ $comment->comment }}</p>
                                
                                @if(!$record->isDiscussionClosed())
                                    <button 
                                        wire:click="setReplyTo({{ $comment->id }})"
                                        class="mt-2 text-sm text-blue-600 hover:text-blue-500">
                                        Reply
                                    </button>
                                @endif
                            </div>

                            <!-- Replies -->
                            @if($comment->replies->count() > 0)
                                <div class="ml-8 mt-3 space-y-2">
                                    @foreach($comment->replies->sortBy('created_at') as $reply)
                                        <div class="bg-white border rounded p-3">
                                            <div class="flex justify-between items-start mb-1">
                                                <p class="text-sm font-medium">{{ $reply->user->name ?? 'Unknown' }}</p>
                                                <p class="text-xs text-gray-500">{{ $reply->created_at->diffForHumans() }}</p>
                                            </div>
                                            <p class="text-sm text-gray-700">{{ $reply->comment }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Reply Form -->
                            @if($replyTo === $comment->id && !$record->isDiscussionClosed())
                                <div class="ml-8 mt-3">
                                    <form wire:submit.prevent="addReply">
                                        <textarea 
                                            wire:model="replyComment" 
                                            rows="2" 
                                            class="w-full border rounded p-2"
                                            placeholder="Tulis balasan..."></textarea>
                                        <div class="mt-2 space-x-2">
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm">
                                                Kirim
                                            </button>
                                            <button 
                                                type="button" 
                                                wire:click="cancelReply"
                                                class="px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm">
                                                Batal
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="text-gray-500">Belum ada komentar. Mulai diskusi dengan menambahkan komentar pertama.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
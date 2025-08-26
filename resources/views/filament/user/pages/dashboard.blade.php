{{-- resources/views/filament/user/pages/dashboard.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Welcome Section --}}
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center text-white">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-user class="w-6 h-6" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-semibold">
                            Welcome back, {{ auth()->user()->name }}!
                        </h3>
                        <p class="text-blue-100">
                            {{ auth()->user()->jabatan }} - {{ auth()->user()->divisi }}
                        </p>
                        <p class="text-sm text-blue-200 mt-1">
                            Last login: {{ auth()->user()->updated_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ \App\Filament\User\Resources\MyDocumentRequestResource::getUrl('create') }}" 
               class="group relative bg-white dark:bg-gray-800 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow">
                <div>
                    <span class="rounded-lg inline-flex p-3 bg-blue-50 text-blue-600 ring-4 ring-white">
                        <x-heroicon-o-document-plus class="h-6 w-6" />
                    </span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Create New Request
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Start a new document request for legal review and approval.
                    </p>
                </div>
                <span class="pointer-events-none absolute top-6 right-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                    <x-heroicon-o-arrow-right class="h-6 w-6" />
                </span>
            </a>

            <a href="{{ \App\Filament\User\Resources\MyApprovalResource::getUrl() }}" 
               class="group relative bg-white dark:bg-gray-800 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow">
                <div>
                    <span class="rounded-lg inline-flex p-3 bg-orange-50 text-orange-600 ring-4 ring-white">
                        <x-heroicon-o-clipboard-document-check class="h-6 w-6" />
                    </span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Pending Approvals
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Review and approve documents waiting for your decision.
                    </p>
                </div>
                <span class="pointer-events-none absolute top-6 right-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                    <x-heroicon-o-arrow-right class="h-6 w-6" />
                </span>
            </a>

            <a href="{{ \App\Filament\User\Resources\DiscussionResource::getUrl() }}" 
               class="group relative bg-white dark:bg-gray-800 p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 rounded-lg shadow hover:shadow-md transition-shadow">
                <div>
                    <span class="rounded-lg inline-flex p-3 bg-green-50 text-green-600 ring-4 ring-white">
                        <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" />
                    </span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Active Discussions
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Participate in ongoing document discussions and forums.
                    </p>
                </div>
                <span class="pointer-events-none absolute top-6 right-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                    <x-heroicon-o-arrow-right class="h-6 w-6" />
                </span>
            </a>
        </div>

        {{-- Personal Stats Overview --}}
        @php
            $user = auth()->user();
            $myDrafts = \App\Models\DocumentRequest::where('nik', $user->nik)->where('is_draft', true)->count();
            $myPending = \App\Models\DocumentRequest::where('nik', $user->nik)->whereIn('status', ['submitted', 'pending_supervisor', 'pending_gm', 'pending_legal'])->count();
            $myCompleted = \App\Models\DocumentRequest::where('nik', $user->nik)->where('status', 'completed')->count();
        @endphp

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                    My Document Summary
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-600">{{ $myDrafts }}</div>
                        <div class="text-sm text-gray-500">Draft Documents</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ $myPending }}</div>
                        <div class="text-sm text-gray-500">Pending Approval</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $myCompleted }}</div>
                        <div class="text-sm text-gray-500">Completed</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Widgets --}}
        {{ $this->getWidgets() }}
    </div>
</x-filament-panels::page>
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-user-circle class="h-12 w-12 text-gray-400" />
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Welcome, {{ auth()->user()->name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ auth()->user()->jabatan }} - {{ auth()->user()->divisi }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($this->getHeaderWidgets() as $widget)
                @livewire($widget)
            @endforeach
        </div>

        <!-- Quick Actions -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route('filament.user.resources.document-requests.create') }}" 
                       class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <x-heroicon-o-document-plus class="h-8 w-8 text-blue-600 mr-3" />
                        <div>
                            <p class="text-sm font-medium text-blue-900">Create New Request</p>
                            <p class="text-xs text-blue-700">Start a new document request</p>
                        </div>
                    </a>

                    <a href="{{ route('filament.user.resources.document-requests.index') }}" 
                       class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <x-heroicon-o-document-text class="h-8 w-8 text-green-600 mr-3" />
                        <div>
                            <p class="text-sm font-medium text-green-900">My Documents</p>
                            <p class="text-xs text-green-700">View all your documents</p>
                        </div>
                    </a>

                    <a href="{{ route('filament.user.resources.agreement-overviews.index') }}" 
                       class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                        <x-heroicon-o-document-check class="h-8 w-8 text-purple-600 mr-3" />
                        <div>
                            <p class="text-sm font-medium text-purple-900">Agreements</p>
                            <p class="text-xs text-purple-700">Manage agreement overviews</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
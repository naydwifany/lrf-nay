{{-- resources/views/filament/admin/widgets/activity-timeline.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activity
        </x-slot>

        <div class="space-y-4">
            @forelse ($activities ?? [] as $activity)
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        @switch($activity->action ?? 'default')
                            @case('created')
                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                                @break
                            @case('updated')
                                <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                                @break
                            @case('deleted')
                                <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                                @break
                            @default
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        @endswitch
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $activity->user_name ?? 'System' }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $activity->description ?? 'No description' }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ isset($activity->created_at) ? $activity->created_at->diffForHumans() : 'Unknown time' }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="text-center py-6">
                    <div class="w-12 h-12 mx-auto mb-4 text-gray-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity.</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
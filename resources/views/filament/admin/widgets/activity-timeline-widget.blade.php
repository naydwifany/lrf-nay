{{-- resources/views/filament/admin/widgets/activity-timeline.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activity
        </x-slot>

        <div class="space-y-4">
            @forelse ($activities as $activity)
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        @switch($activity->action)
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
                            {{ $activity->user_name }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $activity->description }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $activity->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
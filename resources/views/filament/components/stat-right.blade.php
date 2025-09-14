// resources/views/filament/components/stat-right.blade.php

<div class="flex justify-between items-center p-4 rounded-xl bg-white shadow">
    <div>
        <div class="text-sm text-gray-500">{{ $title }}</div>
        <div class="text-xs text-gray-400 flex items-center mt-1">
            @if ($descriptionIcon)
                <x-dynamic-component :component="$descriptionIcon" class="w-4 h-4 mr-1"/>
            @endif
            {{ $description }}
        </div>
    </div>
    <div class="text-xl font-bold text-gray-800">
        {{ $value }}
    </div>
</div>

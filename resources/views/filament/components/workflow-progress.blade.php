{{-- resources/views/filament/components/workflow-progress.blade.php --}}
@php
    $workflowService = $workflowService ?? app(\App\Services\DocumentWorkflowService::class);
    $progress = $workflowService->getAgreementOverviewProgress($record);
    $current_status = $record->status;
@endphp

@style([
    'width' => $progress.'%',
])

<div class="space-y-4">

    <div>
        {{-- Render progress bar dsb --}}
        {{ $current_status }}
    </div>
    <!-- Progress Bar -->
    <div class="w-full bg-gray-200 rounded-full h-2">
        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" @style(['width' => $progress.'%'])></div>
    </div>
    
    <!-- Progress Percentage -->
    <div class="text-center">
        <span class="text-sm font-medium text-gray-700">{{ $progress }}% Complete</span>
    </div>
    
    <!-- Workflow Steps -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
        @php
            $steps = [
                'pending_head' => ['label' => 'Head', 'icon' => '👤'],
                'pending_gm' => ['label' => 'GM', 'icon' => '💼'],
                'pending_finance' => ['label' => 'Finance', 'icon' => '💰'],
                'pending_legal' => ['label' => 'Legal', 'icon' => '⚖️'],
                'pending_director1' => ['label' => 'Director 1', 'icon' => '🎯'],
                'pending_director2' => ['label' => 'Director 2', 'icon' => '✅'],
            ];
            
            $statusOrder = [
                'draft', 'pending_head', 'pending_gm', 'pending_finance', 
                'pending_legal', 'pending_director1', 'pending_director2', 'approved'
            ];
            
            $currentIndex = array_search($current_status, $statusOrder);
        @endphp
        
        @foreach($steps as $status => $step)
            @php
                $stepIndex = array_search($status, $statusOrder);
                $isCompleted = $stepIndex < $currentIndex;
                $isCurrent = $status === $current_status;
                $isPending = $stepIndex > $currentIndex;
            @endphp
            
            <div class="flex items-center space-x-2 p-2 rounded border
                {{ $isCompleted ? 'bg-green-50 border-green-200 text-green-700' : '' }}
                {{ $isCurrent ? 'bg-blue-50 border-blue-200 text-blue-700 ring-2 ring-blue-300' : '' }}
                {{ $isPending ? 'bg-gray-50 border-gray-200 text-gray-500' : '' }}
            ">
                <span class="text-sm">{{ $step['icon'] }}</span>
                <div class="flex-1">
                    <div class="font-medium">{{ $step['label'] }}</div>
                    <div class="text-xs opacity-75">
                        @if($isCompleted)
                            ✓ Completed
                        @elseif($isCurrent)
                            ⏳ In Progress
                        @else
                            ⏸️ Pending
                        @endif
                    </div>
                </div>
                @if($isCompleted)
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                @elseif($isCurrent)
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
                @endif
            </div>
        @endforeach
    </div>
    
    <!-- Current Status Info -->
    <div class="text-center p-3 bg-gray-50 rounded border">
        <div class="text-sm font-medium text-gray-700">
            Current Status: 
            <span class="text-blue-600">
                {{ \App\Models\AgreementOverview::getStatusOptions()[$current_status] ?? $current_status }}
            </span>
        </div>
        @if($current_status === 'approved')
            <div class="text-xs text-green-600 mt-1">🎉 Agreement Overview Fully Approved!</div>
        @elseif($current_status === 'rejected')
            <div class="text-xs text-red-600 mt-1">❌ Agreement Overview Rejected</div>
        @elseif($current_status === 'rediscuss')
            <div class="text-xs text-yellow-600 mt-1">💬 Sent for Re-discussion</div>
        @endif
    </div>
</div>
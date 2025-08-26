<?php
// app/Filament/Admin/Widgets/ApprovalStatsWidget.php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\DocumentApproval;
use Illuminate\Support\Facades\DB;

class ApprovalStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Approval Statistics';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $approvalStats = DocumentApproval::select('approval_type', 'status', DB::raw('count(*) as count'))
            ->groupBy('approval_type', 'status')
            ->get()
            ->groupBy('approval_type');

        $labels = ['Supervisor', 'General Manager', 'Legal', 'Finance', 'Director'];
        $pendingData = [];
        $approvedData = [];
        $rejectedData = [];

        foreach (['supervisor', 'general_manager', 'legal', 'finance', 'director'] as $type) {
            $stats = $approvalStats->get($type, collect());
            
            $pendingData[] = $stats->where('status', 'pending')->sum('count');
            $approvedData[] = $stats->where('status', 'approved')->sum('count');
            $rejectedData[] = $stats->where('status', 'rejected')->sum('count');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pending',
                    'data' => $pendingData,
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Approved',
                    'data' => $approvedData,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Rejected',
                    'data' => $rejectedData,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
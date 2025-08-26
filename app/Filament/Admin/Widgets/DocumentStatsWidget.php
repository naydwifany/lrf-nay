<?php
// app/Filament/Admin/Widgets/DocumentStatsWidget.php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\DocumentRequest;
use App\Models\AgreementOverview;

class DocumentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Document Requests', DocumentRequest::count())
                ->description('All document requests')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            
            Stat::make('Pending Approvals', DocumentRequest::whereIn('status', [
                'pending_supervisor', 'pending_gm', 'pending_legal'
            ])->count())
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Completed Documents', DocumentRequest::where('status', 'completed')->count())
                ->description('Fully completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
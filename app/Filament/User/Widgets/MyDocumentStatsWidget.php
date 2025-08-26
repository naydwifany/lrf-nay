<?php
// app/Filament/User/Widgets/MyDocumentStatsWidget.php

namespace App\Filament\User\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\DocumentRequest;
use App\Models\AgreementOverview;

class MyDocumentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        
        return [
            Stat::make('My Document Requests', DocumentRequest::where('nik', $user->nik)->count())
                ->description('Total documents created')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            
            Stat::make('Draft Documents', DocumentRequest::where('nik', $user->nik)->where('is_draft', true)->count())
                ->description('Documents in draft')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray'),
            
            Stat::make('Pending Approval', DocumentRequest::where('nik', $user->nik)->whereIn('status', [
                'submitted', 'pending_supervisor', 'pending_gm', 'pending_legal'
            ])->count())
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('In Discussion', DocumentRequest::where('nik', $user->nik)->where('status', 'discussion')->count())
                ->description('Active discussions')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),
            
            Stat::make('Completed', DocumentRequest::where('nik', $user->nik)->where('status', 'completed')->count())
                ->description('Successfully completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            
            Stat::make('My Agreements', AgreementOverview::where('nik', $user->nik)->count())
                ->description('Agreement overviews created')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary'),
        ];
    }
}
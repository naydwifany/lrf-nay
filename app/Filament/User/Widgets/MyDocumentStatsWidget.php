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

        \Log::info('DocumentRequests count', [
            'count' => DocumentRequest::where('nik', $user->nik)->count(),
            'nik' => $user->nik,
        ]);

        
    return [
        Stat::make('My Document Requests', DocumentRequest::where('nik', $user->nik)->count())
            ->description('Total documents created')
            ->descriptionIcon('heroicon-m-document-text')
            ->color('primary')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),

        Stat::make('Draft Documents', DocumentRequest::where('nik', $user->nik)->where('is_draft', true)->count())
            ->description('Documents in draft')
            ->descriptionIcon('heroicon-m-pencil-square')
            ->color('gray')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),

        Stat::make('Pending Approval', DocumentRequest::where('nik', $user->nik)->whereIn('status', [
            'submitted', 'pending_supervisor', 'pending_gm', 'pending_legal'
        ])->count())
            ->description('Awaiting approval')
            ->descriptionIcon('heroicon-m-clock')
            ->color('warning')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),

        Stat::make('In Discussion', DocumentRequest::where('nik', $user->nik)->where('status', 'discussion')->count())
            ->description('Active discussions')
            ->descriptionIcon('heroicon-m-chat-bubble-left-right')
            ->color('info')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),

        Stat::make('Completed', DocumentRequest::where('nik', $user->nik)->where('status', 'completed')->count())
            ->description('Successfully completed')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),

        Stat::make('My Agreements', AgreementOverview::where('nik', $user->nik)->count())
            ->description('Agreement overviews created')
            ->descriptionIcon('heroicon-m-document-duplicate')
            ->color('primary')
            ->extraAttributes([
                'class' => 'flex justify-between items-center',
            ]),
    ];
    }
}
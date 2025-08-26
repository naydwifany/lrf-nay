<?php
// app/Filament/User/Widgets/PendingAgreementOverviewStatsWidget.php

namespace App\Filament\User\Widgets;

use App\Models\AgreementOverview;
use App\Services\DocumentWorkflowService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingAgreementOverviewStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Get pending AOs for current user
        $pendingQuery = AgreementOverview::where('is_draft', false)
            ->where(function ($query) use ($user) {
                $query->when($user->role === 'head', function ($q) {
                    $q->where('status', AgreementOverview::STATUS_PENDING_HEAD);
                })
                ->when($user->role === 'general_manager', function ($q) {
                    $q->where('status', AgreementOverview::STATUS_PENDING_GM);
                })
                ->when($user->role === 'finance', function ($q) {
                    $q->where('status', AgreementOverview::STATUS_PENDING_FINANCE);
                })
                ->when($user->role === 'legal_admin', function ($q) {
                    $q->where('status', AgreementOverview::STATUS_PENDING_LEGAL);
                })
                ->when($user->role === 'director', function ($q) use ($user) {
                    $q->where(function ($subQuery) use ($user) {
                        $subQuery->where('status', AgreementOverview::STATUS_PENDING_DIRECTOR1)
                                ->where('nik_direksi', $user->nik);
                    })
                    ->orWhere(function ($subQuery) use ($user) {
                        $subQuery->where('status', AgreementOverview::STATUS_PENDING_DIRECTOR2)
                                ->where('nik_direksi', '!=', $user->nik);
                    });
                });
            });

        $totalPending = $pendingQuery->count();
        $urgentPending = $pendingQuery->where('submitted_at', '<=', now()->subDays(3))->count();
        
        // Get user's own AOs
        $myAOs = AgreementOverview::where('nik', $user->nik);
        $totalMyAOs = $myAOs->count();
        $draftAOs = $myAOs->where('is_draft', true)->count();

        return [
            Stat::make('Pending My Approval', $totalPending)
                ->description($totalPending > 0 ? 'Agreement Overviews waiting' : 'All caught up!')
                ->descriptionIcon($totalPending > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($totalPending > 0 ? 'warning' : 'success')
                ->url(route('filament.user.resources.pending-agreement-overviews.index'))
                ->extraAttributes([
                    'class' => $totalPending > 0 ? 'cursor-pointer hover:bg-gray-50' : ''
                ]),

            Stat::make('Urgent (>3 days)', $urgentPending)
                ->description($urgentPending > 0 ? 'Requires immediate attention' : 'No urgent items')
                ->descriptionIcon($urgentPending > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($urgentPending > 0 ? 'danger' : 'success')
                ->url($urgentPending > 0 ? route('filament.user.resources.pending-agreement-overviews.index') . '?tableFilters[priority][value]=high' : null),

            Stat::make('My AO Drafts', $draftAOs)
                ->description($draftAOs > 0 ? 'Draft Agreement Overviews' : 'No drafts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($draftAOs > 0 ? 'info' : 'gray')
                ->url(route('filament.user.resources.my-agreement-overviews.index')),

            Stat::make('Total My AOs', $totalMyAOs)
                ->description('All my Agreement Overviews')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary')
                ->url(route('filament.user.resources.my-agreement-overviews.index')),
        ];
    }

    public function getDisplayName(): string
    {
        return 'Agreement Overview Statistics';
    }

    protected function getPollingInterval(): ?string
    {
        return '30s'; // Auto-refresh every 30 seconds
    }
}
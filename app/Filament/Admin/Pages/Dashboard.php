<?php
// app/Filament/Admin/Pages/Dashboard.php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Admin\Widgets\DocumentStatsWidget::class,
            \App\Filament\Admin\Widgets\ApprovalStatsWidget::class,
            \App\Filament\Admin\Widgets\ActivityTimelineWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
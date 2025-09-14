<?php
// app/Filament/User/Pages/Dashboard.php

namespace App\Filament\User\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.admin.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            \App\Filament\User\Widgets\MyDocumentStatsWidget::class,
            \App\Filament\User\Widgets\PendingApprovalWidget::class,
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
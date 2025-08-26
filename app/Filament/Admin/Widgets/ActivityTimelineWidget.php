<?php
// app/Filament/Admin/Widgets/ActivityTimelineWidget.php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use App\Models\ActivityLog;

class ActivityTimelineWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.activity-timeline';
    protected static ?int $sort = 3;

    protected function getViewData(): array
    {
        return [
            'activities' => ActivityLog::with('user')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}
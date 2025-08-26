<?php
// app/Filament/Admin/Resources/ActivityLogResource/Pages/ViewActivityLog.php

namespace App\Filament\Admin\Resources\ActivityLogResource\Pages;

use App\Filament\Admin\Resources\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions for activity logs
        ];
    }
}
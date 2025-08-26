<?php
// app/Filament/User/Resources/MyHistoryResource/Pages/ViewMyHistory.php

namespace App\Filament\User\Resources\MyHistoryResource\Pages;

use App\Filament\User\Resources\MyHistoryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMyHistory extends ViewRecord
{
    protected static string $resource = MyHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // History is read-only
        ];
    }
}
<?php
// app/Filament/User/Resources/MyHistoryResource/Pages/ListMyHistory.php

namespace App\Filament\User\Resources\MyHistoryResource\Pages;

use App\Filament\User\Resources\MyHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListMyHistory extends ListRecords
{
    protected static string $resource = MyHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action for history
        ];
    }
}
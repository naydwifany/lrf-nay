<?php
// app/Filament/User/Resources/MyApprovalResource/Pages/ViewMyApproval.php

namespace App\Filament\User\Resources\MyApprovalResource\Pages;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMyApproval extends ViewRecord
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Custom approval actions can be added here
        ];
    }
}
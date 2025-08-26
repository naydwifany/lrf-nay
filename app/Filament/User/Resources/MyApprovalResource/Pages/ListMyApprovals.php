<?php
// app/Filament/User/Resources/MyApprovalResource/Pages/ListMyApprovals.php

namespace App\Filament\User\Resources\MyApprovalResource\Pages;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListMyApprovals extends ListRecords
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action for approvals
        ];
    }
}
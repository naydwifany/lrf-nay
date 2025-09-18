<?php
// app/Filament/User/Resources/MyApprovalResource/Pages/ViewMyApproval.php

namespace App\Filament\User\Resources\MyApprovalResource\Pages;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Resources\Pages\Page;

class ViewMyApproval extends Page
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Custom approval actions can be added here
        ];
    }

    protected function getInfolist()
    {
        return MyApprovalResource::infolist($this->record, 'lrf'); // ⚠️ ganti 'lrf' untuk LRF
    }
}
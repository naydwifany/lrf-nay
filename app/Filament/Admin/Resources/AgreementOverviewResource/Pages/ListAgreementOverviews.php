<?php
// app/Filament/Admin/Resources/AgreementOverviewResource/Pages/ListAgreementOverviews.php

namespace App\Filament\Admin\Resources\AgreementOverviewResource\Pages;

use App\Filament\Admin\Resources\AgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgreementOverviews extends ListRecords
{
    protected static string $resource = AgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Agreement Overview')
                ->icon('heroicon-o-plus'),
        ];
    }
}
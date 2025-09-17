<?php
// app/Filament/Admin/Resources/DocumentRequestResource/Pages/ListAgreementOverviews.php

namespace App\Filament\Admin\Resources\DocumentRequestResource\Pages\AgreementOverviews;

use App\Filament\Admin\Resources\DocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgreementOverview extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    /*
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Agreement Overview')
                ->icon('heroicon-o-plus'),
        ];
    }
    */
}
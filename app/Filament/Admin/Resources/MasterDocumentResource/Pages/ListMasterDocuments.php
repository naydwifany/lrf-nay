<?php
// app/Filament/Admin/Resources/MasterDocumentResource/Pages/ListMasterDocuments.php

namespace App\Filament\Admin\Resources\MasterDocumentResource\Pages;

use App\Filament\Admin\Resources\MasterDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMasterDocuments extends ListRecords
{
    protected static string $resource = MasterDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Document Type'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add stats widget if needed
        ];
    }
}
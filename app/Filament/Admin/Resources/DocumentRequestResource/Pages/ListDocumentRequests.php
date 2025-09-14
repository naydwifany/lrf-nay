<?php

namespace App\Filament\Admin\Resources\DocumentRequestResource\Pages;

use App\Filament\Admin\Resources\DocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    /*
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    */
}

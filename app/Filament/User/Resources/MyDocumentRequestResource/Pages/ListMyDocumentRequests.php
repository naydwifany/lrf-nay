<?php
// app/Filament/User/Resources/MyDocumentRequestResource/Pages/ListMyDocumentRequests.php

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages;

use App\Filament\User\Resources\MyDocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMyDocumentRequests extends ListRecords
{
    protected static string $resource = MyDocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Document Request'),
        ];
    }
}
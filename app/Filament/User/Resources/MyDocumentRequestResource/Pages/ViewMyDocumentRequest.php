<?php
// app/Filament/User/Resources/MyDocumentRequestResource/Pages/ViewMyDocumentRequest.php

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages;

use App\Filament\User\Resources\MyDocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMyDocumentRequest extends ViewRecord
{
    protected static string $resource = MyDocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->is_draft),
        ];
    }
}
<?php
// app/Filament/Admin/Resources/MasterDocumentResource/Pages/CreateMasterDocument.php

namespace App\Filament\Admin\Resources\MasterDocumentResource\Pages;

use App\Filament\Admin\Resources\MasterDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterDocument extends CreateRecord
{
    protected static string $resource = MasterDocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate document code if empty
        if (empty($data['document_code'])) {
            $data['document_code'] = strtoupper(substr(str_replace(' ', '', $data['document_name']), 0, 5));
        }

        return $data;
    }
}
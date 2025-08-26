<?php
// app/Filament/Admin/Resources/MasterDocumentResource/Pages/EditMasterDocument.php

namespace App\Filament\Admin\Resources\MasterDocumentResource\Pages;

use App\Filament\Admin\Resources\MasterDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMasterDocument extends EditRecord
{
    protected static string $resource = MasterDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('preview')
                ->label('Preview Form')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function () {
                    // Logic to preview form based on fields configuration
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
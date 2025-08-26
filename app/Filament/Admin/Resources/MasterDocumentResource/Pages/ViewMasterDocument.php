<?php
// app/Filament/Admin/Resources/MasterDocumentResource/Pages/ViewMasterDocument.php

namespace App\Filament\Admin\Resources\MasterDocumentResource\Pages;

use App\Filament\Admin\Resources\MasterDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMasterDocument extends ViewRecord
{
    protected static string $resource = MasterDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->action(function () {
                    $newRecord = $this->record->replicate();
                    $newRecord->document_name = $this->record->document_name . ' (Copy)';
                    $newRecord->document_code = $this->record->document_code . '_COPY';
                    $newRecord->is_active = false;
                    $newRecord->save();
                    
                    return redirect()->to(static::getResource()::getUrl('edit', ['record' => $newRecord]));
                }),
        ];
    }
}
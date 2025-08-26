<?php 
// app/Filament/Admin/Resources/DocumentCommentResource/Pages/EditDocumentComment.php

namespace App\Filament\Admin\Resources\DocumentCommentResource\Pages;

use App\Filament\Admin\Resources\DocumentCommentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentComment extends EditRecord
{
    protected static string $resource = DocumentCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => !$this->record->is_forum_closed),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
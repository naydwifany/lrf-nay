<?php
// app/Filament/Admin/Resources/DocumentCommentResource/Pages/ViewDocumentComment.php

namespace App\Filament\Admin\Resources\DocumentCommentResource\Pages;

use App\Filament\Admin\Resources\DocumentCommentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentComment extends ViewRecord
{
    protected static string $resource = DocumentCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => !$this->record->is_forum_closed),
        ];
    }
}
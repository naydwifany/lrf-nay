<?php

namespace App\Filament\Admin\Resources\DocumentCommentResource\Pages;

use App\Filament\Admin\Resources\DocumentCommentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentComment extends CreateRecord
{
    protected static string $resource = DocumentCommentResource::class;
}

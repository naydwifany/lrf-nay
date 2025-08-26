<?php
// app/Filament/Admin/Resources/DocumentCommentResource/Pages/ListDocumentComments.php

namespace App\Filament\Admin\Resources\DocumentCommentResource\Pages;

use App\Filament\Admin\Resources\DocumentCommentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentComments extends ListRecords
{
    protected static string $resource = DocumentCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Remove CreateAction since we disabled manual creation
        ];
    }
    
    public function getTitle(): string
    {
        return 'Discussion Comments';
    }
    
    public function getHeading(): string 
    {
        return 'All Discussion Comments';
    }
}



// ============================================================


<?php
// In app/Http/Controllers/DiscussionController.php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function show($document_id)
    {
        $document = DocumentRequest::findOrFail($document_id);
        
        // Load comments dengan eager loading untuk performance
        $comments = $document->comments()->orderBy('created_at', 'asc')->get();
        
        // Optional: Load discussion stats atau data lain yang dibutuhkan
        $discussionStats = [
            'total_comments' => $comments->count(),
            'participants' => $comments->unique('user_nik')->count()
        ];

        return view('filament.admin.resources.document-request-resource.pages.view-discussion', compact('document', 'comments'));
    }
}

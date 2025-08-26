<?php

// app/Console/Commands/DebugDiscussion.php
// FIXED VERSION - Handle null attachments

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\DocumentComment;
use App\Models\DocumentCommentAttachment;
use App\Services\DocumentDiscussionService;

class DebugDiscussion extends Command
{
    protected $signature = 'debug:discussion {document_id?}';
    protected $description = 'Debug discussion attachments';

    public function handle()
    {
        $this->info('🔍 DEBUGGING DISCUSSION ATTACHMENTS');
        $this->info('=====================================');

        // Step 1: Basic counts
        $this->info('📊 Step 1: Basic Counts');
        $this->line('Documents: ' . DocumentRequest::count());
        $this->line('Comments: ' . DocumentComment::count());
        $this->line('Attachments: ' . DocumentCommentAttachment::count());
        $this->newLine();

        // Step 2: Check specific document or find one with attachments
        $documentId = $this->argument('document_id') ?? 9;
        $this->info("📄 Step 2: Checking Document #{$documentId}");
        
        $doc = DocumentRequest::find($documentId);
        if (!$doc) {
            $this->warn("Document #{$documentId} not found!");
            
            // Find any document with attachments
            $docWithAttachments = DocumentRequest::whereHas('comments.attachments')->first();
            if ($docWithAttachments) {
                $doc = $docWithAttachments;
                $this->info("Using document #{$doc->id} instead (has attachments)");
            } else {
                $this->error('No documents with attachments found!');
                return;
            }
        }

        $this->line("Title: {$doc->title}");
        $this->line("Status: {$doc->status}");
        $this->line("Comments: " . $doc->comments()->count());
        $this->newLine();

        // Step 3: Check comments with attachments - FIXED
        $this->info('💬 Step 3: Comments Detail');
        $comments = $doc->comments()->with('attachments')->get();
        
        foreach ($comments as $comment) {
            $this->line("Comment #{$comment->id} by {$comment->user_name}");
            $this->line("  Content: " . substr($comment->comment, 0, 50) . "...");
            
            // FIXED: Handle null attachments
            if ($comment->relationLoaded('attachments') && $comment->attachments !== null) {
                $attachmentCount = $comment->attachments->count();
                $this->line("  Attachments: {$attachmentCount}");
                
                foreach ($comment->attachments as $attachment) {
                    $this->line("    📎 {$attachment->original_filename}");
                    $this->line("       Size: {$attachment->file_size} bytes");
                    $this->line("       Path: {$attachment->file_path}");
                    $exists = \Storage::disk('private')->exists($attachment->file_path);
                    $this->line("       File exists: " . ($exists ? 'YES' : 'NO'));
                }
            } else {
                // Force load if not loaded
                $comment->load('attachments');
                $attachmentCount = $comment->attachments ? $comment->attachments->count() : 0;
                $this->line("  Attachments: {$attachmentCount} (force loaded)");
                
                if ($comment->attachments && $comment->attachments->count() > 0) {
                    foreach ($comment->attachments as $attachment) {
                        $this->line("    📎 {$attachment->original_filename}");
                        $this->line("       Size: {$attachment->file_size} bytes");
                        $this->line("       Path: {$attachment->file_path}");
                        $exists = \Storage::disk('private')->exists($attachment->file_path);
                        $this->line("       File exists: " . ($exists ? 'YES' : 'NO'));
                    }
                }
            }
        }
        $this->newLine();

        // Step 4: Check raw attachment data
        $this->info('📎 Step 4: Raw Attachment Data');
        $attachments = DocumentCommentAttachment::whereHas('comment', function($q) use ($doc) {
            $q->where('document_request_id', $doc->id);
        })->get();
        
        $this->line("Found {$attachments->count()} attachments for this document:");
        foreach ($attachments as $att) {
            $this->line("  #{$att->id}: {$att->original_filename} (Comment #{$att->document_comment_id})");
        }
        $this->newLine();

        // Step 5: Test service - FIXED
        $this->info('🔧 Step 5: Test Discussion Service');
        try {
            $service = app(DocumentDiscussionService::class);
            
            $stats = $service->getDiscussionStats($doc);
            $this->line("Stats loaded successfully");
            $this->line("Total attachments in stats: {$stats['total_attachments']}");
            
            $timeline = $service->getDiscussionTimeline($doc);
            $this->line("Timeline loaded: " . count($timeline) . " items");
            
            foreach ($timeline as $item) {
                $attachmentCount = isset($item['attachments']) ? count($item['attachments']) : 0;
                $this->line("Comment #{$item['id']} has {$attachmentCount} attachments in timeline");
                
                if ($attachmentCount > 0) {
                    foreach ($item['attachments'] as $att) {
                        $this->line("  - {$att['name']} ({$att['size']})");
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->error('Service error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile());
            $this->error('Line: ' . $e->getLine());
        }
        $this->newLine();

        // Step 6: Test route
        $this->info('🔗 Step 6: Test Routes');
        $firstAttachment = DocumentCommentAttachment::whereHas('comment', function($q) use ($doc) {
            $q->where('document_request_id', $doc->id);
        })->first();
        
        if ($firstAttachment) {
            try {
                $url = route('discussion.attachment.download', $firstAttachment->id);
                $this->line("Download URL: {$url}");
            } catch (\Exception $e) {
                $this->error("Route error: " . $e->getMessage());
            }
        } else {
            $this->warn("No attachments found for route testing");
        }

        $this->newLine();
        $this->info('✅ Debug completed!');
        $this->info("🌐 Visit: /admin/document-requests/{$doc->id}/discussion");
        
        // Final recommendation
        if ($attachments->count() > 0) {
            $this->warn("🎯 ISSUE FOUND: Attachments exist in DB but relationships not loading properly!");
            $this->line("📋 Solutions:");
            $this->line("1. Update DocumentDiscussionService.php with fixed version");
            $this->line("2. Update DocumentComment.php model with proper relationships");
            $this->line("3. Replace view template with fixed version");
        }
    }
}
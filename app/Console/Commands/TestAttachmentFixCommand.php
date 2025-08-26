<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\DocumentComment;

class TestAttachmentFixCommand extends Command
{
    protected $signature = 'test:attachment-fix {document-id}';
    protected $description = 'Test the attachment fix for column name conflict';

    public function handle()
    {
        $documentId = $this->argument('document-id');
        
        $this->info("🧪 TESTING ATTACHMENT FIX FOR DOCUMENT {$documentId}");
        $this->line('================================================');
        
        $document = DocumentRequest::find($documentId);
        if (!$document) {
            $this->error("Document {$documentId} not found!");
            return;
        }
        
        $this->newLine();
        
        // 1. Test old vs new relationship approach
        $this->info('1️⃣ TESTING RELATIONSHIP APPROACHES');
        $this->line('==================================');
        
        $comments = DocumentComment::where('document_request_id', $documentId)->limit(3)->get();
        
        foreach ($comments as $comment) {
            $this->line("Testing Comment {$comment->id}:");
            
            // Test new attachmentFiles relationship
            try {
                $attachmentFiles = $comment->attachmentFiles;
                $attachmentCount = $attachmentFiles ? $attachmentFiles->count() : 0;
                $this->line("  - attachmentFiles relationship: {$attachmentCount} files");
                $this->line("  - attachmentFiles is null: " . (is_null($attachmentFiles) ? 'YES' : 'NO'));
                $this->line("  - attachmentFiles type: " . gettype($attachmentFiles));
                
                if ($attachmentFiles && $attachmentCount > 0) {
                    foreach ($attachmentFiles as $file) {
                        $this->line("    * {$file->original_filename}");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ attachmentFiles error: " . $e->getMessage());
            }
            
            // Test accessor
            try {
                $attachments = $comment->attachments; // This uses the accessor
                $attachmentCount = $attachments ? $attachments->count() : 0;
                $this->line("  - attachments accessor: {$attachmentCount} files");
                $this->line("  - attachments is null: " . (is_null($attachments) ? 'YES' : 'NO'));
                $this->line("  - attachments type: " . gettype($attachments));
                
            } catch (\Exception $e) {
                $this->error("  ❌ attachments accessor error: " . $e->getMessage());
            }
            
            // Test direct relationship query
            try {
                $directCount = $comment->attachmentFiles()->count();
                $this->line("  - direct attachmentFiles()->count(): {$directCount}");
            } catch (\Exception $e) {
                $this->error("  ❌ direct query error: " . $e->getMessage());
            }
            
            // Test raw column (should be different)
            try {
                $rawAttachments = $comment->getAttributeValue('attachments');
                $this->line("  - raw attachments column: " . (is_null($rawAttachments) ? 'NULL' : 'HAS DATA'));
            } catch (\Exception $e) {
                $this->error("  ❌ raw column error: " . $e->getMessage());
            }
            
            $this->newLine();
        }
        
        // 2. Test ViewDiscussion approach with new relationship
        $this->info('2️⃣ TESTING VIEWDISCUSSION APPROACH');
        $this->line('==================================');
        
        $viewComments = $document->comments()
            ->whereNull('parent_id')
            ->with([
                'attachmentFiles' => function($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'replies' => function($query) {
                    $query->with('attachmentFiles')->orderBy('created_at', 'asc');
                }
            ])
            ->latest()
            ->get();
            
        $this->line("ViewDiscussion comments loaded: " . $viewComments->count());
        
        $totalAttachments = 0;
        foreach ($viewComments as $comment) {
            $attachments = $comment->attachments; // Uses accessor
            $attachmentCount = $attachments ? $attachments->count() : 0;
            $totalAttachments += $attachmentCount;
            
            $this->line("Comment {$comment->id}: {$attachmentCount} attachments");
            
            if ($attachmentCount > 0) {
                foreach ($attachments as $attachment) {
                    $this->line("  - {$attachment->original_filename} ({$attachment->getFormattedFileSize()})");
                }
            }
        }
        
        $this->line("Total attachments found: {$totalAttachments}");
        
        // 3. Compare with expected count
        $this->info('3️⃣ VERIFICATION');
        $this->line('===============');
        
        $expectedCount = \DB::select("
            SELECT COUNT(*) as count
            FROM document_comment_attachments dca
            JOIN document_comments dc ON dca.document_comment_id = dc.id
            WHERE dc.document_request_id = ?
            AND dc.deleted_at IS NULL
        ", [$documentId])[0]->count;
        
        $this->line("Expected attachments (from DB): {$expectedCount}");
        $this->line("Found via relationship: {$totalAttachments}");
        
        if ($totalAttachments === $expectedCount) {
            $this->info("✅ SUCCESS! Attachment loading fixed!");
        } else {
            $this->error("❌ Still has issues. Expected: {$expectedCount}, Found: {$totalAttachments}");
        }
        
        $this->newLine();
        
        // 4. Test attachment download URLs
        if ($totalAttachments > 0) {
            $this->info('4️⃣ TESTING DOWNLOAD URLS');
            $this->line('========================');
            
            $firstComment = $viewComments->first();
            $attachments = $firstComment->attachments;
            
            if ($attachments && $attachments->count() > 0) {
                $firstAttachment = $attachments->first();
                
                try {
                    $downloadUrl = route('discussion.attachment.download', $firstAttachment->id);
                    $this->line("Sample download URL: {$downloadUrl}");
                    $this->info("✅ Download URL generation working");
                } catch (\Exception $e) {
                    $this->error("❌ Download URL error: " . $e->getMessage());
                }
            }
        }
        
        $this->newLine();
        $this->info('✅ Attachment fix test completed!');
        
        if ($totalAttachments === $expectedCount && $totalAttachments > 0) {
            $this->info('🎉 All attachment issues should now be resolved!');
            $this->line('You can now test the user panel discussion view.');
        }
    }
}
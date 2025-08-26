<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\DocumentComment;
use App\Models\DocumentCommentAttachment;

class DebugAttachmentRelationshipCommand extends Command
{
    protected $signature = 'debug:attachment-rel {document-id}';
    protected $description = 'Debug attachment relationship mismatch';

    public function handle()
    {
        $documentId = $this->argument('document-id');
        
        $this->info("🔍 DEBUGGING ATTACHMENT RELATIONSHIP FOR DOCUMENT {$documentId}");
        $this->line('==============================================================');
        
        $document = DocumentRequest::find($documentId);
        if (!$document) {
            $this->error("Document {$documentId} not found!");
            return;
        }
        
        $this->newLine();
        
        // 1. Raw database check
        $this->info('📊 RAW DATABASE QUERIES');
        $this->line('========================');
        
        // Comments for this document
        $commentsRaw = \DB::select("
            SELECT id, comment, user_name, document_request_id, parent_id 
            FROM document_comments 
            WHERE document_request_id = ? 
            AND deleted_at IS NULL
            ORDER BY created_at DESC
        ", [$documentId]);
        
        $this->line("Raw comments count: " . count($commentsRaw));
        foreach ($commentsRaw as $comment) {
            $this->line("  Comment {$comment->id}: {$comment->user_name} (parent: {$comment->parent_id})");
        }
        
        $this->newLine();
        
        // Attachments for this document - WITHOUT soft delete check first
        $attachmentsRawQuery = "
            SELECT dca.*, dc.document_request_id 
            FROM document_comment_attachments dca
            JOIN document_comments dc ON dca.document_comment_id = dc.id
            WHERE dc.document_request_id = ?
        ";
        
        // Check if tables have deleted_at columns
        $commentColumnsCheck = \DB::select("SHOW COLUMNS FROM document_comments LIKE 'deleted_at'");
        $attachmentColumnsCheck = \DB::select("SHOW COLUMNS FROM document_comment_attachments LIKE 'deleted_at'");
        
        $commentHasSoftDelete = !empty($commentColumnsCheck);
        $attachmentHasSoftDelete = !empty($attachmentColumnsCheck);
        
        $this->line("Comments table has soft deletes: " . ($commentHasSoftDelete ? 'YES' : 'NO'));
        $this->line("Attachments table has soft deletes: " . ($attachmentHasSoftDelete ? 'YES' : 'NO'));
        
        // Adjust query based on soft delete columns
        if ($commentHasSoftDelete) {
            $attachmentsRawQuery .= " AND dc.deleted_at IS NULL";
        }
        if ($attachmentHasSoftDelete) {
            $attachmentsRawQuery .= " AND dca.deleted_at IS NULL";
        }
        
        $attachmentsRaw = \DB::select($attachmentsRawQuery, [$documentId]);
        
        $this->line("Raw attachments count: " . count($attachmentsRaw));
        foreach ($attachmentsRaw as $attachment) {
            $this->line("  Attachment {$attachment->id}: {$attachment->original_filename} (comment: {$attachment->document_comment_id})");
        }
        
        $this->newLine();
        
        // 2. Check relationship loading issues
        $this->info('🔗 RELATIONSHIP LOADING TEST');
        $this->line('=============================');
        
        // Test 1: Load comments without eager loading
        $comments = DocumentComment::where('document_request_id', $documentId)->get();
        $this->line("Comments loaded (no eager): " . $comments->count());
        
        foreach ($comments as $comment) {
            $attachments = $comment->attachments; // This will trigger lazy loading
            $this->line("  Comment {$comment->id}: {$attachments->count()} attachments (lazy)");
        }
        
        $this->newLine();
        
        // Test 2: Load with explicit eager loading
        $comments = DocumentComment::with('attachments')->where('document_request_id', $documentId)->get();
        $this->line("Comments loaded (with eager): " . $comments->count());
        
        foreach ($comments as $comment) {
            $attachments = $comment->attachments;
            $this->line("  Comment {$comment->id}: {$attachments->count()} attachments (eager)");
            $this->line("    - Relation loaded: " . ($comment->relationLoaded('attachments') ? 'YES' : 'NO'));
        }
        
        $this->newLine();
        
        // 3. Check foreign key integrity
        $this->info('🔑 FOREIGN KEY INTEGRITY CHECK');
        $this->line('==============================');
        
        foreach ($commentsRaw as $comment) {
            $attachmentCount = \DB::select("
                SELECT COUNT(*) as count 
                FROM document_comment_attachments 
                WHERE document_comment_id = ? AND deleted_at IS NULL
            ", [$comment->id])[0]->count;
            
            $this->line("Comment {$comment->id} should have: {$attachmentCount} attachments");
        }
        
        $this->newLine();
        
        // 4. Test DocumentCommentAttachment model relationship
        $this->info('🏗️  MODEL RELATIONSHIP TEST');
        $this->line('===========================');
        
        $attachments = DocumentCommentAttachment::whereHas('comment', function($q) use ($documentId) {
            $q->where('document_request_id', $documentId);
        })->with('comment')->get();
        
        $this->line("Attachments found via model: " . $attachments->count());
        
        foreach ($attachments as $attachment) {
            $comment = $attachment->comment;
            $this->line("Attachment {$attachment->id}: {$attachment->original_filename}");
            $this->line("  - Comment ID: {$attachment->document_comment_id}");
            $this->line("  - Comment loaded: " . ($comment ? 'YES' : 'NO'));
            $this->line("  - Comment belongs to doc: " . ($comment ? $comment->document_request_id : 'NULL'));
        }
        
        $this->newLine();
        
        // 5. Check DocumentComment model relationship method
        $this->info('📝 COMMENT MODEL RELATIONSHIP CHECK');
        $this->line('===================================');
        
        $testComment = DocumentComment::where('document_request_id', $documentId)->first();
        if ($testComment) {
            $this->line("Testing comment {$testComment->id}");
            
            // Check if attachments() method exists and works
            try {
                $attachmentsViaRelation = $testComment->attachments();
                $this->line("  - attachments() method exists: YES");
                $this->line("  - SQL: " . $attachmentsViaRelation->toSql());
                $this->line("  - Count: " . $attachmentsViaRelation->count());
                
                $actualAttachments = $attachmentsViaRelation->get();
                foreach ($actualAttachments as $att) {
                    $this->line("    * {$att->original_filename}");
                }
                
            } catch (\Exception $e) {
                $this->error("  - attachments() method error: " . $e->getMessage());
            }
        } else {
            $this->warn("No comments found to test relationship");
        }
        
        $this->newLine();
        
        // 6. Check table schema
        $this->info('🏗️  TABLE SCHEMA CHECK');
        $this->line('======================');
        
        $commentColumns = \DB::select("DESCRIBE document_comments");
        $this->line("document_comments columns:");
        foreach ($commentColumns as $col) {
            $this->line("  - {$col->Field} ({$col->Type})");
        }
        
        $this->newLine();
        
        $attachmentColumns = \DB::select("DESCRIBE document_comment_attachments");
        $this->line("document_comment_attachments columns:");
        foreach ($attachmentColumns as $col) {
            $this->line("  - {$col->Field} ({$col->Type})");
        }
        
        $this->newLine();
        
        // 7. Diagnosis
        $this->info('🔍 DIAGNOSIS');
        $this->line('=============');
        
        $totalAttachmentsRaw = count($attachmentsRaw);
        $totalCommentsRaw = count($commentsRaw);
        
        if ($totalAttachmentsRaw > 0 && $totalCommentsRaw > 0) {
            $this->info("✅ Data exists in database");
            
            // Test specific relationship
            $problemComment = null;
            foreach ($commentsRaw as $commentRaw) {
                $comment = DocumentComment::find($commentRaw->id);
                $attachmentCount = $comment->attachments()->count();
                
                if ($attachmentCount == 0) {
                    $rawAttachmentCount = \DB::table('document_comment_attachments')
                        ->where('document_comment_id', $commentRaw->id)
                        ->whereNull('deleted_at')
                        ->count();
                        
                    if ($rawAttachmentCount > 0) {
                        $problemComment = $commentRaw->id;
                        $this->warn("⚠️  Comment {$commentRaw->id} has {$rawAttachmentCount} attachments in DB but 0 via relationship");
                        break;
                    }
                }
            }
            
            if ($problemComment) {
                $this->error("❌ Relationship broken for comment {$problemComment}");
                $this->line("💡 Check DocumentComment model's attachments() relationship method");
            } else {
                $this->info("✅ Relationships working correctly");
            }
            
        } else {
            $this->warn("⚠️  No data found in database");
        }
        
        $this->newLine();
        $this->info('✅ Debug completed!');
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckTableStructureCommand extends Command
{
    protected $signature = 'check:tables';
    protected $description = 'Check table structure for comments and attachments';

    public function handle()
    {
        $this->info('🏗️  CHECKING TABLE STRUCTURES');
        $this->line('=============================');
        
        // Check document_comments table
        $this->info('📝 DOCUMENT_COMMENTS TABLE');
        $this->line('===========================');
        
        try {
            $commentColumns = \DB::select("DESCRIBE document_comments");
            $this->table(['Field', 'Type', 'Null', 'Key', 'Default'], 
                array_map(function($col) {
                    return [
                        $col->Field,
                        $col->Type,
                        $col->Null,
                        $col->Key ?? '',
                        $col->Default ?? ''
                    ];
                }, $commentColumns)
            );
            
            // Check for soft deletes
            $hasSoftDelete = collect($commentColumns)->contains(function($col) {
                return $col->Field === 'deleted_at';
            });
            $this->line("Has soft deletes: " . ($hasSoftDelete ? '✅ YES' : '❌ NO'));
            
        } catch (\Exception $e) {
            $this->error("❌ Error checking document_comments: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Check document_comment_attachments table
        $this->info('📎 DOCUMENT_COMMENT_ATTACHMENTS TABLE');
        $this->line('=====================================');
        
        try {
            $attachmentColumns = \DB::select("DESCRIBE document_comment_attachments");
            $this->table(['Field', 'Type', 'Null', 'Key', 'Default'], 
                array_map(function($col) {
                    return [
                        $col->Field,
                        $col->Type,
                        $col->Null,
                        $col->Key ?? '',
                        $col->Default ?? ''
                    ];
                }, $attachmentColumns)
            );
            
            // Check for soft deletes
            $hasSoftDelete = collect($attachmentColumns)->contains(function($col) {
                return $col->Field === 'deleted_at';
            });
            $this->line("Has soft deletes: " . ($hasSoftDelete ? '✅ YES' : '❌ NO'));
            
            // Check foreign key column
            $hasForeignKey = collect($attachmentColumns)->contains(function($col) {
                return $col->Field === 'document_comment_id';
            });
            $this->line("Has document_comment_id foreign key: " . ($hasForeignKey ? '✅ YES' : '❌ NO'));
            
        } catch (\Exception $e) {
            $this->error("❌ Error checking document_comment_attachments: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Check data counts
        $this->info('📊 DATA COUNTS');
        $this->line('==============');
        
        try {
            $commentsCount = \DB::table('document_comments')->count();
            $this->line("Total comments: {$commentsCount}");
            
            $attachmentsCount = \DB::table('document_comment_attachments')->count();
            $this->line("Total attachments: {$attachmentsCount}");
            
            // Check foreign key integrity
            $orphanAttachments = \DB::select("
                SELECT COUNT(*) as count 
                FROM document_comment_attachments dca
                LEFT JOIN document_comments dc ON dca.document_comment_id = dc.id
                WHERE dc.id IS NULL
            ")[0]->count;
            
            $this->line("Orphan attachments: {$orphanAttachments}");
            
        } catch (\Exception $e) {
            $this->error("❌ Error checking data counts: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Test simple join
        $this->info('🔗 TESTING SIMPLE JOIN');
        $this->line('======================');
        
        try {
            $joinTest = \DB::select("
                SELECT 
                    dc.id as comment_id,
                    dc.comment,
                    COUNT(dca.id) as attachment_count
                FROM document_comments dc
                LEFT JOIN document_comment_attachments dca ON dc.id = dca.document_comment_id
                WHERE dc.document_request_id IN (SELECT id FROM document_requests WHERE status IN ('discussion', 'in_discussion') LIMIT 3)
                GROUP BY dc.id
                LIMIT 10
            ");
            
            $this->line("Sample comment-attachment relationships:");
            foreach ($joinTest as $result) {
                $this->line("  Comment {$result->comment_id}: {$result->attachment_count} attachments");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing join: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Check specific document 10
        $this->info('🎯 CHECKING DOCUMENT 10 SPECIFICALLY');
        $this->line('===================================');
        
        try {
            $doc10Data = \DB::select("
                SELECT 
                    dc.id as comment_id,
                    dc.user_name,
                    dc.comment,
                    COUNT(dca.id) as attachment_count
                FROM document_comments dc
                LEFT JOIN document_comment_attachments dca ON dc.id = dca.document_comment_id
                WHERE dc.document_request_id = 10
                GROUP BY dc.id, dc.user_name, dc.comment
                ORDER BY dc.created_at DESC
            ");
            
            foreach ($doc10Data as $result) {
                $this->line("Comment {$result->comment_id} by {$result->user_name}: {$result->attachment_count} attachments");
                $preview = substr($result->comment, 0, 50) . (strlen($result->comment) > 50 ? '...' : '');
                $this->line("  Content: {$preview}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error checking document 10: " . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('✅ Table structure check completed!');
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;

class CompareAdminUserPanelsCommand extends Command
{
    protected $signature = 'compare:panels {document-id}';
    protected $description = 'Compare how admin vs user panels load the same document';

    public function handle()
    {
        $documentId = $this->argument('document-id');
        
        $this->info("🔄 COMPARING ADMIN VS USER PANELS FOR DOCUMENT {$documentId}");
        $this->line('===========================================================');
        
        $document = DocumentRequest::find($documentId);
        if (!$document) {
            $this->error("Document {$documentId} not found!");
            return;
        }
        
        $this->newLine();
        
        // Test both panel approaches
        $this->info('🔍 TESTING BOTH PANEL APPROACHES');
        $this->line('=================================');
        
        // Admin panel approach (if exists)
        try {
            if (class_exists('App\Filament\Admin\Resources\DiscussionResource\Pages\ViewDiscussion')) {
                $this->line("✅ Admin ViewDiscussion class exists");
            } else {
                $this->line("❌ Admin ViewDiscussion class not found");
            }
        } catch (\Exception $e) {
            $this->line("❌ Admin ViewDiscussion error: " . $e->getMessage());
        }
        
        // User panel approach
        try {
            if (class_exists('App\Filament\User\Resources\DiscussionResource\Pages\ViewDiscussion')) {
                $this->line("✅ User ViewDiscussion class exists");
                
                // Test instantiation
                $reflection = new \ReflectionClass('App\Filament\User\Resources\DiscussionResource\Pages\ViewDiscussion');
                $this->line("✅ User ViewDiscussion can be reflected");
                
                // Check for getComments method
                if ($reflection->hasMethod('getComments')) {
                    $this->line("✅ User ViewDiscussion has getComments method");
                } else {
                    $this->line("❌ User ViewDiscussion missing getComments method");
                }
                
            } else {
                $this->line("❌ User ViewDiscussion class not found");
            }
        } catch (\Exception $e) {
            $this->line("❌ User ViewDiscussion error: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Test service approach directly
        $this->info('🛠️  TESTING SERVICE DIRECTLY');
        $this->line('============================');
        
        try {
            $service = app(\App\Services\DocumentDiscussionService::class);
            $stats = $service->getDiscussionStats($document);
            
            $this->line("Service stats:");
            $this->line("  - Total comments: " . ($stats['total_comments'] ?? 'NULL'));
            $this->line("  - Total attachments: " . ($stats['total_attachments'] ?? 'NULL'));
            $this->line("  - Finance participated: " . (($stats['finance_participated'] ?? false) ? 'YES' : 'NO'));
            
        } catch (\Exception $e) {
            $this->error("❌ Service error: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Test direct model approach
        $this->info('📊 TESTING DIRECT MODEL APPROACH');
        $this->line('=================================');
        
        $comments = $document->comments()
            ->whereNull('parent_id')
            ->with('attachments')
            ->get();
            
        $this->line("Direct model comments: " . $comments->count());
        
        foreach ($comments as $comment) {
            $attachmentCount = $comment->attachments ? $comment->attachments->count() : 0;
            $this->line("  Comment {$comment->id}: {$attachmentCount} attachments");
            
            if ($attachmentCount > 0) {
                foreach ($comment->attachments as $attachment) {
                    $this->line("    - {$attachment->original_filename}");
                }
            }
        }
        
        $this->newLine();
        
        // Check current user context
        $this->info('👤 CURRENT USER CONTEXT');
        $this->line('=======================');
        
        $user = auth()->user();
        if ($user) {
            $this->line("User: {$user->name} ({$user->nik})");
            $this->line("Role: {$user->role}");
            
            $service = app(\App\Services\DocumentDiscussionService::class);
            $canAccess = $service->canUserAccessDiscussion($document, $user);
            $canParticipate = $service->canUserParticipate($user);
            
            $this->line("Can access: " . ($canAccess ? 'YES' : 'NO'));
            $this->line("Can participate: " . ($canParticipate ? 'YES' : 'NO'));
        } else {
            $this->line("❌ No user logged in");
        }
        
        $this->newLine();
        $this->info('✅ Comparison completed!');
        
        $this->newLine();
        $this->info('💡 TROUBLESHOOTING SUGGESTIONS:');
        $this->line('1. Check if user panel view file exists in correct path');
        $this->line('2. Verify User ViewDiscussion class has all methods');
        $this->line('3. Compare logs between admin and user panel access');
        $this->line('4. Test with same user account in both panels');
    }
}
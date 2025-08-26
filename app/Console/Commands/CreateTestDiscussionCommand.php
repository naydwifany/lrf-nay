<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\User;

class CreateTestDiscussionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'create:test-discussion {--doc-id= : Specific document ID to convert} {--count=1 : Number of documents to convert}';

    /**
     * The console command description.
     */
    protected $description = 'Create test discussion documents by converting existing documents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 CREATING TEST DISCUSSION DATA...');
        $this->newLine();

        $docId = $this->option('doc-id');
        $count = (int) $this->option('count');

        if ($docId) {
            $this->convertSpecificDocument($docId);
        } else {
            $this->convertRandomDocuments($count);
        }

        $this->newLine();
        $this->info('✅ Test data creation completed!');
        $this->info('💡 Run "php artisan check:discussion" to verify the data');
    }

    private function convertSpecificDocument($docId)
    {
        $document = DocumentRequest::find($docId);
        
        if (!$document) {
            $this->error("Document with ID {$docId} not found!");
            return;
        }

        $this->info("Converting document ID {$docId}: {$document->title}");
        
        $oldStatus = $document->status;
        $document->update(['status' => 'in_discussion']);
        
        $this->info("✅ Status changed from '{$oldStatus}' to 'in_discussion'");
        
        // Add some test comments
        $this->addTestComments($document);
    }

    private function convertRandomDocuments($count)
    {
        // Get documents that are not already in discussion
        $documents = DocumentRequest::whereNotIn('status', ['discussion', 'in_discussion'])
            ->limit($count)
            ->get();

        if ($documents->isEmpty()) {
            $this->warn('No documents found to convert!');
            
            // Show available documents
            $available = DocumentRequest::limit(10)->get(['id', 'title', 'status']);
            if ($available->count() > 0) {
                $this->info('Available documents:');
                $this->table(['ID', 'Title', 'Current Status'], 
                    $available->map(fn($doc) => [
                        $doc->id,
                        substr($doc->title, 0, 40) . '...',
                        $doc->status
                    ])->toArray()
                );
            }
            return;
        }

        $this->info("Converting {$documents->count()} documents to discussion status:");

        foreach ($documents as $document) {
            $oldStatus = $document->status;
            $document->update(['status' => 'in_discussion']);
            
            $this->info("✅ Doc {$document->id}: '{$document->title}' ({$oldStatus} → in_discussion)");
            
            // Add some test comments
            $this->addTestComments($document);
        }
    }

    private function addTestComments(DocumentRequest $document)
    {
        // Get users with different roles for testing
        $headLegal = User::where('role', 'head_legal')->first();
        $finance = User::where('role', 'finance')->first();
        $generalManager = User::where('role', 'general_manager')->first();
        
        $testComments = [];
        
        if ($headLegal) {
            $testComments[] = [
                'user_id' => $headLegal->id,
                'user_nik' => $headLegal->nik,
                'user_name' => $headLegal->name,
                'user_role' => $headLegal->role,
                'comment' => 'Legal review: Document structure looks good. Need finance input on budget allocation.',
                'is_forum_closed' => false,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ];
        }
        
        if ($finance) {
            $testComments[] = [
                'user_id' => $finance->id,
                'user_nik' => $finance->nik,
                'user_name' => $finance->name,
                'user_role' => $finance->role,
                'comment' => 'Finance review: Budget allocation approved. Total amount is within acceptable range.',
                'is_forum_closed' => false,
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ];
        }
        
        if ($generalManager) {
            $testComments[] = [
                'user_id' => $generalManager->id,
                'user_nik' => $generalManager->nik,
                'user_name' => $generalManager->name,
                'user_role' => $generalManager->role,
                'comment' => 'Management approval: Please proceed with this document. Looks good from strategic perspective.',
                'is_forum_closed' => false,
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ];
        }
        
        // Add requester comment
        $requester = User::where('nik', $document->nik)->first();
        if ($requester) {
            $testComments[] = [
                'user_id' => $requester->id,
                'user_nik' => $requester->nik,
                'user_name' => $requester->name,
                'user_role' => $requester->role,
                'comment' => 'Thank you for the reviews. All feedback has been noted and will be implemented.',
                'is_forum_closed' => false,
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ];
        }
        
        foreach ($testComments as $commentData) {
            $document->comments()->create($commentData);
        }
        
        if (count($testComments) > 0) {
            $this->line("   💬 Added " . count($testComments) . " test comments");
        }
    }
}
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocumentDiscussionService;

class TestDiscussionCommand extends Command
{
    protected $signature = 'test:discussion {document_id}';
    protected $description = 'Test discussion functionality';

    public function handle()
    {
        $documentId = $this->argument('document_id');
        $document = DocumentRequest::findOrFail($documentId);
        $user = User::first(); // Use first user for testing
        
        $service = app(DocumentDiscussionService::class);
        
        // Test add comment
        $this->info('Testing add comment...');
        try {
            $comment = $service->addComment($document, $user, 'Test comment from command', []);
            $this->info("✅ Comment added with ID: {$comment->id}");
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
        }
        
        // Test discussion stats
        $this->info('Testing discussion stats...');
        $stats = $service->getDiscussionStats($document);
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Comments', $stats['total_comments']],
                ['Total Attachments', $stats['total_attachments']],
                ['Participants', $stats['participants_count']],
                ['Finance Participated', $stats['finance_participated'] ? 'Yes' : 'No'],
                ['Can Be Closed', $stats['can_be_closed'] ? 'Yes' : 'No'],
            ]
        );
        
        $this->info('✅ Discussion test completed!');
    }
}
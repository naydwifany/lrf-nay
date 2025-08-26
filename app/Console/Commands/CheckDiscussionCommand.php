<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocumentDiscussionService;

class CheckDiscussionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'check:discussion {--user-nik= : Check for specific user NIK}';

    /**
     * The console command description.
     */
    protected $description = 'Check discussion documents and user access for debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 CHECKING DISCUSSION FORUM DATA...');
        $this->newLine();

        // 1. Check all available statuses
        $this->checkDocumentStatuses();
        
        // 2. Check discussion documents
        $this->checkDiscussionDocuments();
        
        // 3. Check user access
        $userNik = $this->option('user-nik');
        if ($userNik) {
            $user = User::where('nik', $userNik)->first();
            if ($user) {
                $this->checkUserAccess($user);
            } else {
                $this->error("User with NIK '{$userNik}' not found!");
            }
        } else {
            $this->info('💡 Tip: Use --user-nik=<nik> to check specific user access');
        }
        
        // 4. Check service functionality
        $this->checkServiceFunctionality();
        
        $this->newLine();
        $this->info('✅ Check completed!');
    }

    private function checkDocumentStatuses()
    {
        $this->info('📊 DOCUMENT STATUSES');
        $this->line('==================');
        
        $statuses = DocumentRequest::distinct('status')->pluck('status')->toArray();
        
        if (empty($statuses)) {
            $this->warn('No documents found in database!');
            return;
        }
        
        $this->table(['Status', 'Count'], collect($statuses)->map(function($status) {
            return [
                $status,
                DocumentRequest::where('status', $status)->count()
            ];
        })->toArray());
        
        $this->newLine();
    }

    private function checkDiscussionDocuments()
    {
        $this->info('💬 DISCUSSION DOCUMENTS');
        $this->line('=======================');
        
        $discussionDocs = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])
            ->with(['comments', 'doctype'])
            ->get();
            
        if ($discussionDocs->isEmpty()) {
            $this->warn('❌ No documents found with discussion or in_discussion status!');
            $this->line('   This is why the Discussion Forum is empty.');
            $this->newLine();
            
            // Show recent documents for reference
            $this->info('📋 Recent documents (any status):');
            $recent = DocumentRequest::latest()->limit(5)->get(['id', 'title', 'status', 'nama']);
            if ($recent->count() > 0) {
                $this->table(['ID', 'Title', 'Status', 'Requester'], 
                    $recent->map(fn($doc) => [
                        $doc->id,
                        substr($doc->title, 0, 40) . '...',
                        $doc->status,
                        $doc->nama
                    ])->toArray()
                );
            }
            return;
        }
        
        $this->info("✅ Found {$discussionDocs->count()} discussion documents:");
        
        $tableData = $discussionDocs->map(function($doc) {
            return [
                $doc->id,
                substr($doc->title, 0, 30) . '...',
                $doc->status,
                $doc->nama,
                $doc->comments()->count(),
                $doc->doctype->document_name ?? 'No Type'
            ];
        })->toArray();
        
        $this->table(['ID', 'Title', 'Status', 'Requester', 'Comments', 'Type'], $tableData);
        $this->newLine();
    }

    private function checkUserAccess($user)
    {
        $this->info("👤 USER ACCESS CHECK: {$user->name} ({$user->nik})");
        $this->line('===========================================');
        
        $this->table(['Property', 'Value'], [
            ['NIK', $user->nik],
            ['Name', $user->name],
            ['Role', $user->role],
            ['Division', $user->divisi ?? 'No Division'],
        ]);
        
        try {
            $service = app(DocumentDiscussionService::class);
            
            // Check if user can participate
            $canParticipate = $service->canUserParticipate($user);
            $this->line("Can participate in discussions: " . ($canParticipate ? '✅ YES' : '❌ NO'));
            
            if (!$canParticipate) {
                $this->warn('User role is not allowed to participate in discussions!');
                $this->line('Allowed roles: head_legal, general_manager, reviewer_legal, finance, admin_legal, head, senior_manager, manager, supervisor');
                $this->newLine();
                return;
            }
            
            // Check access to specific documents
            $discussionDocs = DocumentRequest::whereIn('status', ['discussion', 'in_discussion'])->get();
            
            if ($discussionDocs->isEmpty()) {
                $this->warn('No discussion documents to check access for.');
                return;
            }
            
            $this->info("\n🔐 ACCESS TO DISCUSSION DOCUMENTS:");
            
            $accessData = [];
            foreach ($discussionDocs as $doc) {
                try {
                    // Debug individual access check
                    $this->line("Checking access for document {$doc->id}...");
                    
                    $canAccess = $service->canUserAccessDiscussion($doc, $user);
                    
                    // Check specific access reasons
                    $isRequester = $doc->nik === $user->nik;
                    $hasApproved = $doc->approvals()->where('approver_nik', $user->nik)->exists();
                    $hasCommented = $doc->comments()->where('user_nik', $user->nik)->exists();
                    
                    $reasons = [];
                    if ($isRequester) $reasons[] = 'Requester';
                    if ($hasApproved) $reasons[] = 'Approved';
                    if ($hasCommented) $reasons[] = 'Commented';
                    if (in_array($user->role, ['head_legal', 'reviewer_legal', 'finance', 'general_manager', 'admin_legal'])) {
                        $reasons[] = 'Privileged Role';
                    }
                    
                    $accessData[] = [
                        $doc->id,
                        substr($doc->title, 0, 25) . '...',
                        $canAccess ? '✅ YES' : '❌ NO',
                        implode(', ', $reasons) ?: 'None'
                    ];
                    
                } catch (\Exception $e) {
                    $this->error("Error checking access for document {$doc->id}: " . $e->getMessage());
                    $accessData[] = [
                        $doc->id,
                        substr($doc->title, 0, 25) . '...',
                        '❌ ERROR',
                        'Error: ' . $e->getMessage()
                    ];
                }
            }
            
            $this->table(['Doc ID', 'Title', 'Access', 'Reason'], $accessData);
            
        } catch (\Exception $e) {
            $this->error('Error during access check: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
        
        $this->newLine();
    }

    private function checkServiceFunctionality()
    {
        $this->info('⚙️  SERVICE FUNCTIONALITY CHECK');
        $this->line('===============================');
        
        try {
            $service = app(DocumentDiscussionService::class);
            $this->info('✅ DocumentDiscussionService loaded successfully');
            
            // Test basic methods
            $methods = [
                'canUserParticipate',
                'canUserAccessDiscussion', 
                'hasFinanceParticipated',
                'canCloseDiscussion'
            ];
            
            foreach ($methods as $method) {
                if (method_exists($service, $method)) {
                    $this->info("✅ Method {$method} exists");
                } else {
                    $this->error("❌ Method {$method} missing!");
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ DocumentDiscussionService failed to load!');
            $this->error('Error: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
}
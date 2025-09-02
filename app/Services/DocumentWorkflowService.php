<?php

// app/Services/DocumentWorkflowService.php - FIXED VERSION

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\AgreementOverview;
use App\Models\DocumentApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentWorkflowService
{
    /**
     * Submit document for approval workflow
     */
     public function submitDocument(DocumentRequest $document, $user): bool
    {
        try {
            DB::beginTransaction();

            // Generate document number
            if (!$document->nomor_dokumen) {
                $document->update([
                    'nomor_dokumen' => $this->generateDocumentNumber($document)
                ]);
            }

            // Update document status
            $document->update([
                'status' => DocumentRequest::STATUS_PENDING_SUPERVISOR,
                'submitted_at' => now(),
                'is_draft' => false,
                'submitted_by' => $user->id ?? null,
            ]);

            // Create supervisor approval
            $this->createSupervisorApproval($document);

            DB::commit();
            Log::info("Document {$document->id} submitted - Supervisor approval created");
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error submitting document {$document->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create supervisor approval from same division
     */
    private function createSupervisorApproval(DocumentRequest $document): void
    {
        $supervisorNik = $document->nik_atasan;
        
        if (!$supervisorNik) {
            throw new \Exception("Supervisor NIK not found for document: {$document->id}");
        }

        // Find supervisor in same division
        $supervisor = User::where('nik', $supervisorNik)
            ->where('divisi', $document->divisi) // SAME DIVISION ONLY
            ->where('is_active', true)
            ->first();
            
        if (!$supervisor) {
            throw new \Exception("Supervisor not found or not in same division. NIK: {$supervisorNik}, Division: {$document->divisi}");
        }

        // Create single approval record
        DocumentApproval::create([
            'document_request_id' => $document->id,
            'approval_type' => DocumentApproval::TYPE_SUPERVISOR, // Generic supervisor type
            'approver_nik' => $supervisorNik,
            'approver_name' => $supervisor->name,
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => 1,
            'division_level' => $document->divisi,
            'is_division_approval' => true,
        ]);

        Log::info("Supervisor approval created", [
            'document_id' => $document->id,
            'supervisor_nik' => $supervisorNik,
            'supervisor_name' => $supervisor->name,
            'supervisor_role' => $supervisor->role,
            'supervisor_jabatan' => $supervisor->jabatan,
            'division' => $document->divisi
        ]);
    }

    /**
     * FIXED: Approve method with supervisor level logic
     */
    public function approve(DocumentRequest $documentRequest, User $approver, string $notes = null): DocumentApproval
    {
        return DB::transaction(function () use ($documentRequest, $approver, $notes) {
            
            // Find pending approval for this user
            $approval = $documentRequest->approvals()
                ->where('approver_nik', $approver->nik)
                ->where('status', DocumentApproval::STATUS_PENDING)
                ->first();

            if (!$approval) {
                throw new \Exception("No pending approval found for user {$approver->nik} on document {$documentRequest->id}");
            }

            // Validate permission
            if (!$this->canUserApproveDocument($documentRequest, $approver)) {
                throw new \Exception("User {$approver->nik} is not authorized to approve this document");
            }

            // Approve current step
            $approval->update([
                'status' => DocumentApproval::STATUS_APPROVED,
                'approved_at' => now(),
                'comments' => $notes,
            ]);

            // FIXED: Level-based workflow logic
            $this->processLevelBasedWorkflow($documentRequest, $approval, $approver);

            Log::info("Document approved with level-based logic", [
                'document_id' => $documentRequest->id,
                'approver_nik' => $approver->nik,
                'approver_role' => $approver->role,
                'approver_jabatan' => $approver->jabatan,
                'new_status' => $documentRequest->fresh()->status
            ]);
            
            return $approval;
        });
    }

    /**
     * FIXED: Level-based workflow processing
     */
    private function processLevelBasedWorkflow(DocumentRequest $document, DocumentApproval $approval, User $approver): void
    {
        Log::info("Processing level-based workflow", [
            'approval_type' => $approval->approval_type,
            'approver_role' => $approver->role,
            'approver_jabatan' => $approver->jabatan,
            'is_senior_level' => $this->isSeniorManagerLevel($approver)
        ]);

        switch ($approval->approval_type) {
            case DocumentApproval::TYPE_SUPERVISOR:
                // Check supervisor level
                if ($this->isSeniorManagerLevel($approver)) {
                    // Supervisor IS Senior Manager/GM - Skip GM, go to Legal
                    Log::info("Supervisor is Senior Manager/GM level - going directly to Legal Admin");
                    $this->createLegalAdminApproval($document);
                    $document->update(['status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN]);
                } else {
                    // Supervisor is regular level - Need Senior Manager approval
                    Log::info("Supervisor is regular level - need Senior Manager approval");
                    $this->createSeniorManagerApproval($document);
                    $document->update(['status' => DocumentRequest::STATUS_PENDING_GM]);
                }
                break;

            case DocumentApproval::TYPE_SENIOR_MANAGER:
            case DocumentApproval::TYPE_GENERAL_MANAGER:
                // Senior Manager/GM approved - go to Legal
                Log::info("Senior Manager/GM approved - going to Legal Admin");
                $this->createLegalAdminApproval($document);
                $document->update(['status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN]);
                break;

            case DocumentApproval::TYPE_ADMIN_LEGAL:
                // Legal admin approved - start discussion
                Log::info("Legal admin approved - starting discussion");
                $this->startDiscussionForum($document, $approver);
                $document->update(['status' => DocumentRequest::STATUS_IN_DISCUSSION]);
                break;

            default:
                throw new \Exception("Unknown approval type: {$approval->approval_type}");
        }
    }

    /**
     * FIXED: Check if user is Senior Manager level
     */
    private function isSeniorManagerLevel(User $user): bool
    {
        // Check by role
        $seniorRoles = ['senior_manager', 'general_manager', 'director'];
        if (in_array($user->role, $seniorRoles)) {
            return true;
        }

        // Check by jabatan (position title)
        $jabatan = strtolower($user->jabatan ?? '');
        $seniorKeywords = [
            'senior manager', 'senior_manager',
            'general manager', 'general_manager', 'gm',
            'kepala divisi', 'kadiv', 'head of division',
            'director', 'direktur', 'vice president', 'vp'
        ];

        foreach ($seniorKeywords as $keyword) {
            if (str_contains($jabatan, $keyword)) {
                Log::info("User identified as Senior Manager level", [
                    'user_nik' => $user->nik,
                    'role' => $user->role,
                    'jabatan' => $jabatan,
                    'matched_keyword' => $keyword
                ]);
                return true;
            }
        }

        // Check by level (if you have numeric level field)
        if (isset($user->level) && $user->level >= 6) {
            return true;
        }

        Log::info("User identified as regular level", [
            'user_nik' => $user->nik,
            'role' => $user->role,
            'jabatan' => $jabatan,
            'level' => $user->level ?? 'N/A'
        ]);

        return false;
    }

    /**
     * Create Senior Manager approval (when needed)
     */
    private function createSeniorManagerApproval(DocumentRequest $document): void
    {
        // Find Senior Manager/GM from same division first
        $seniorManager = User::where('divisi', $document->divisi)
            ->where(function($query) {
                $query->whereIn('role', ['senior_manager', 'general_manager'])
                      ->orWhere('jabatan', 'like', '%senior manager%')
                      ->orWhere('jabatan', 'like', '%general manager%')
                      ->orWhere('jabatan', 'like', '%kepala divisi%')
                      ->orWhere('level', '>=', 6);
            })
            ->where('is_active', true)
            ->first();

        // If no senior manager in same division, find company-wide
        if (!$seniorManager) {
            $seniorManager = User::whereIn('role', ['senior_manager', 'general_manager'])
                ->where('is_active', true)
                ->first();
        }
            
        if (!$seniorManager) {
            throw new \Exception("No active Senior Manager/General Manager found");
        }

        $nextOrder = $document->approvals()->max('order') + 1;

        DocumentApproval::create([
            'document_request_id' => $document->id,
            'approval_type' => $this->getSeniorManagerApprovalType($seniorManager),
            'approver_nik' => $seniorManager->nik,
            'approver_name' => $seniorManager->name,
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => $nextOrder,
            'is_division_approval' => $seniorManager->divisi === $document->divisi,
        ]);

        Log::info("Senior Manager approval created", [
            'senior_manager_nik' => $seniorManager->nik,
            'senior_manager_name' => $seniorManager->name,
            'senior_manager_role' => $seniorManager->role,
            'senior_manager_jabatan' => $seniorManager->jabatan,
            'same_division' => $seniorManager->divisi === $document->divisi,
            'order' => $nextOrder
        ]);
    }

    /**
     * Get appropriate approval type for senior manager
     */
    private function getSeniorManagerApprovalType(User $seniorManager): string
    {
        if (in_array($seniorManager->role, ['general_manager', 'director'])) {
            return DocumentApproval::TYPE_GENERAL_MANAGER;
        }
        return DocumentApproval::TYPE_SENIOR_MANAGER;
    }

    /**
     * Create Legal Admin approval
     */
    private function createLegalAdminApproval(DocumentRequest $document): void
    {
        $legalAdmin = User::whereIn('role', ['admin_legal', 'legal_admin', 'legal'])
            ->where('is_active', true)
            ->first();
            
        if (!$legalAdmin) {
            throw new \Exception("No active Legal Admin found");
        }

        $nextOrder = $document->approvals()->max('order') + 1;

        DocumentApproval::create([
            'document_request_id' => $document->id,
            'approval_type' => DocumentApproval::TYPE_ADMIN_LEGAL,
            'approver_nik' => $legalAdmin->nik,
            'approver_name' => $legalAdmin->name,
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => $nextOrder,
            'is_division_approval' => false,
        ]);

        Log::info("Legal Admin approval created", [
            'legal_nik' => $legalAdmin->nik,
            'legal_name' => $legalAdmin->name,
            'order' => $nextOrder
        ]);
    }

    /**
     * FIXED: Permission check with division validation
     */
    public function canUserApproveDocument(DocumentRequest $document, User $user): bool
    {
        // Cannot approve own document
        if ($document->nik === $user->nik) {
            return false;
        }

        // Must have pending approval record
        $hasPendingApproval = $document->approvals()
            ->where('approver_nik', $user->nik)
            ->where('status', DocumentApproval::STATUS_PENDING)
            ->exists();

        if (!$hasPendingApproval) {
            return false;
        }

        // Status-based validation
        return match ($document->status) {
            DocumentRequest::STATUS_PENDING_SUPERVISOR => 
                // FIXED: Must be supervisor from SAME DIVISION
                $user->nik === $document->nik_atasan && 
                $user->divisi === $document->divisi,
                
            DocumentRequest::STATUS_PENDING_GM => 
                // Senior Manager or GM level
                $this->isSeniorManagerLevel($user),
                
            DocumentRequest::STATUS_PENDING_LEGAL_ADMIN => 
                // Legal team
                in_array($user->role, ['admin_legal', 'legal_admin', 'head_legal', 'legal']),
                
            default => false
        };
    }

    /**
     * Start discussion forum
     */
    private function startDiscussionForum(DocumentRequest $document, User $approver): void
    {
        try {
            \App\Models\DocumentComment::create([
                'document_request_id' => $document->id,
                'user_id' => $approver->id,
                'user_nik' => $approver->nik,
                'user_name' => $approver->name,
                'user_role' => 'admin_legal',
                'comment' => 'Discussion forum has been opened by Legal Admin. All authorized participants may now join the discussion.',
                'is_forum_closed' => false,
            ]);

            Log::info("Discussion forum started for document {$document->id}");
        } catch (\Exception $e) {
            Log::error("Error starting discussion forum: " . $e->getMessage());
        }
    }

    /**
     * DEBUG: Get workflow preview for user
     */
    public function getWorkflowPreview(User $user): array
    {
        $supervisor = User::where('nik', $user->supervisor_nik ?? '')->first();
        
        $steps = ['Document Submission'];
        
        if ($supervisor) {
            if ($this->isSeniorManagerLevel($supervisor)) {
                $steps[] = $supervisor->name . ' (Senior Manager/GM) - Same Division';
                $steps[] = 'Legal Admin Review';
            } else {
                $steps[] = $supervisor->name . ' (Supervisor) - Same Division';
                $steps[] = 'Senior Manager/GM Approval';
                $steps[] = 'Legal Admin Review';
            }
        } else {
            $steps[] = 'Supervisor (from same division)';
            $steps[] = 'Senior Manager/GM (if supervisor is not senior level)';
            $steps[] = 'Legal Admin Review';
        }
        
        $steps[] = 'Discussion Forum';
        $steps[] = 'Agreement Overview Creation';

        return [
            'steps' => $steps,
            'supervisor_info' => [
                'nik' => $supervisor?->nik,
                'name' => $supervisor?->name,
                'role' => $supervisor?->role,
                'jabatan' => $supervisor?->jabatan,
                'is_senior_level' => $supervisor ? $this->isSeniorManagerLevel($supervisor) : false,
                'same_division' => $supervisor?->divisi === $user->divisi
            ]
        ];
    }

    /**
     * Create initial approval record based on requester hierarchy
     */
    private function createInitialApproval(DocumentRequest $document): void
    {
        $requester = User::where('nik', $document->nik)->first();
        
        if (!$requester) {
            throw new \Exception("Requester not found for NIK: {$document->nik}");
        }

        // Get supervisor NIK from document or user record
        $supervisorNik = $document->nik_atasan ?? $requester->supervisor_nik ?? null;
        
        if (!$supervisorNik) {
            throw new \Exception("No supervisor found for user: {$requester->name}");
        }

        // Check if supervisor exists
        $supervisor = User::where('nik', $supervisorNik)->first();
        if (!$supervisor) {
            throw new \Exception("Supervisor not found for NIK: {$supervisorNik}");
        }

        // Determine approval type based on supervisor role
        $approvalType = $this->determineInitialApprovalType($supervisor);

        // Create approval record
        DocumentApproval::create([
            'document_request_id' => $document->id,
            'approval_type' => $approvalType,
            'approver_nik' => $supervisorNik,
            'approver_name' => $supervisor->name,
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => 1,
            'created_at' => now(),
        ]);

        Log::info("Initial approval created for document {$document->id} - Approver: {$supervisor->name} ({$supervisorNik}), Type: {$approvalType}");
    }

    /**
     * Determine initial approval type based on supervisor role
     */
    private function determineInitialApprovalType(User $supervisor): string
    {
        return match($supervisor->role) {
            'general_manager' => DocumentApproval::TYPE_GENERAL_MANAGER,
            'senior_manager' => DocumentApproval::TYPE_SENIOR_MANAGER,
            'manager' => DocumentApproval::TYPE_MANAGER,
            'supervisor' => DocumentApproval::TYPE_SUPERVISOR,
            default => DocumentApproval::TYPE_SUPERVISOR
        };
    }
    // protected function startDiscussionForum(DocumentRequest $documentRequest, User $approver): void
    // {
    //     // Create initial system comment to open the discussion
    //     $documentRequest->comments()->create([
    //         'user_nik' => $approver->nik,
    //         'user_name' => $approver->name,
    //         'user_role' => $approver->role,
    //         'comment' => 'Discussion forum has been opened by Legal Admin. All authorized roles may now participate in the discussion.',
    //         'is_forum_closed' => false,
    //     ]);

    //     $documentRequest->comments()->create([
    //         'user_nik' => 'SYSTEM',
    //         'user_name' => 'System',
    //         'user_role' => 'system',
    //         'comment' => $this->getDiscussionWelcomeMessage(),
    //         'is_forum_closed' => false,
    //     ]);
    //     \Log::info("Discussion forum opened for document {$documentRequest->id} by {$approver->name}");
    // }
    /**
     * Approve document and move to next stage
     */
    /**
 * Approve document and move to next stage - UPDATED VERSION
 */


/**
 * Determine approval type based on document status and approver role - ADDED
 */
private function determineApprovalTypeFromStatus(string $documentStatus, User $approver): string
{
    return match($documentStatus) {
        DocumentRequest::STATUS_PENDING_SUPERVISOR => match($approver->role) {
            'general_manager' => DocumentApproval::TYPE_GENERAL_MANAGER,
            'senior_manager' => DocumentApproval::TYPE_SENIOR_MANAGER,
            'manager' => DocumentApproval::TYPE_MANAGER,
            default => DocumentApproval::TYPE_SUPERVISOR
        },
        DocumentRequest::STATUS_PENDING_GM => DocumentApproval::TYPE_GENERAL_MANAGER,
        DocumentRequest::STATUS_PENDING_LEGAL_ADMIN => DocumentApproval::TYPE_ADMIN_LEGAL,
        default => DocumentApproval::TYPE_SUPERVISOR
    };
}

/**
 * Get approval order based on document status - ADDED
 */
private function getApprovalOrderFromStatus(string $documentStatus): int
{
    return match($documentStatus) {
        DocumentRequest::STATUS_PENDING_SUPERVISOR => 1,
        DocumentRequest::STATUS_PENDING_GM => 2, 
        DocumentRequest::STATUS_PENDING_LEGAL_ADMIN => 3,
        default => 1
    };
}



private function getUserRoleFromApprover(User $approver): string
{
    // Map user roles to discussion roles
    $roleMap = [
        'head' => 'head',
        'general_manager' => 'general_manager',
        'reviewer_legal' => 'reviewer_legal',
        'finance' => 'finance',
        'head_legal' => 'head_legal',
        'admin_legal' => 'admin_legal',
        'legal_admin' => 'admin_legal', // alias
        'Admin' => 'admin_legal', // dari role name
        'Manager' => 'general_manager'
    ];

    foreach ($roleMap as $role => $value) {
        if ($approver->hasRole($role)) {
            return $value;
        }
    }

    return 'approver';
}

    /**
     * Determine next workflow stage
     */
    private function determineNextStage(DocumentRequest $document, string $currentApprovalType, User $approver): ?array
    {
        switch ($currentApprovalType) {
            case DocumentApproval::TYPE_SUPERVISOR:
            case DocumentApproval::TYPE_MANAGER:
                // Check if approver is already GM
                if ($approver->role === 'general_manager') {
                    // Skip GM approval, go directly to legal admin
                    return [
                        'status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN,
                        'next_approval' => [
                            'type' => DocumentApproval::TYPE_ADMIN_LEGAL,
                            'role' => 'admin_legal'
                        ]
                    ];
                } else {
                    // Need GM approval
                    return [
                        'status' => DocumentRequest::STATUS_PENDING_GM,
                        'next_approval' => [
                            'type' => DocumentApproval::TYPE_GENERAL_MANAGER,
                            'role' => 'general_manager'
                        ]
                    ];
                }

            case DocumentApproval::TYPE_SENIOR_MANAGER:
                // Senior manager approved, check if they're also GM
                if ($approver->role === 'general_manager') {
                    return [
                        'status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN,
                        'next_approval' => [
                            'type' => DocumentApproval::TYPE_ADMIN_LEGAL,
                            'role' => 'admin_legal'
                        ]
                    ];
                } else {
                    return [
                        'status' => DocumentRequest::STATUS_PENDING_GM,
                        'next_approval' => [
                            'type' => DocumentApproval::TYPE_GENERAL_MANAGER,
                            'role' => 'general_manager'
                        ]
                    ];
                }

            case DocumentApproval::TYPE_GENERAL_MANAGER:
                return [
                    'status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN,
                    'next_approval' => [
                        'type' => DocumentApproval::TYPE_ADMIN_LEGAL,
                        'role' => 'admin_legal'
                    ]
                ];

            case DocumentApproval::TYPE_ADMIN_LEGAL:
                // Legal admin approved, move to discussion
                return [
                    'status' => DocumentRequest::STATUS_IN_DISCUSSION,
                    'next_approval' => null // Discussion doesn't need approval record
                ];

            default:
                return null; // End of workflow
        }
    }

    /**
     * Create next approval record
     */
    private function createNextApproval(DocumentRequest $document, array $approvalConfig, int $order): void
    {
        // Find user with specified role
        $nextApprover = User::where('role', $approvalConfig['role'])->first();
        
        if (!$nextApprover) {
            throw new \Exception("No user found with role: {$approvalConfig['role']}");
        }

        DocumentApproval::create([
            'document_request_id' => $document->id,
            'approval_type' => $approvalConfig['type'],
            'approver_nik' => $nextApprover->nik,
            'approver_name' => $nextApprover->name,
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => $order,
            'created_at' => now(),
        ]);

        Log::info("Next approval created for document {$document->id} - Approver: {$nextApprover->name} ({$nextApprover->nik}), Type: {$approvalConfig['type']}");
    }

    /**
     * Reject document
     */
    public function reject(DocumentRequest $documentRequest, User $approver, string $notes): DocumentApproval
    {
        return DB::transaction(function () use ($documentRequest, $approver, $notes) {
            // Find pending approval for this user
            $approval = $documentRequest->approvals()
                ->where('approver_nik', $approver->nik)
                ->where('status', DocumentApproval::STATUS_PENDING)
                ->first();

            if (!$approval) {
                throw new \Exception('No pending approval found for this user.');
            }

            // Update approval record
            $approval->update([
                'status' => DocumentApproval::STATUS_REJECTED,
                'approved_at' => now(),
                'comments' => $notes,
            ]);

            // Update document status
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_REJECTED,
                'completed_at' => now()
            ]);

            Log::info("Document {$documentRequest->id} rejected by {$approver->name} ({$approver->nik}): {$notes}");
            
            return $approval;
        });
    }

    /**
     * Check if user can approve the document
     */
    public function canApprove(DocumentRequest $documentRequest, User $user): bool
    {
        // User cannot approve their own request
        if ($documentRequest->nik === $user->nik) {
            return false;
        }

        // Check if user has pending approval for this document
        return $documentRequest->approvals()
            ->where('approver_nik', $user->nik)
            ->where('status', DocumentApproval::STATUS_PENDING)
            ->exists();
    }

    

    /**
     * Get discussion welcome message
     */
    protected function getDiscussionWelcomeMessage(): string
    {
        return "Welcome to the discussion forum for this document request.\n\n" .
               "Authorized participants:\n" .
               "• Head/General Manager\n" .
               "• Legal Reviewer\n" .
               "• Finance Team\n" .
               "• Head Legal\n\n" .
               "Please note:\n" .
               "• Finance team participation is required before the discussion can be closed\n" .
               "• Only Head Legal can close the discussion\n" .
               "• You may attach files to your comments\n" .
               "• Latest comments appear at the top\n\n" .
               "Please share your feedback, questions, or concerns regarding this document request.";
    }


    public function openDiscussionForum(DocumentRequest $document, User $approver)
    {
        try {
            // Create opening comment when legal admin approves
            \App\Models\DocumentComment::create([
                'document_request_id' => $document->id,
                'user_id' => $approver->id,
                'user_nik' => $approver->nik,
                'user_role' => 'admin_legal',
                'parent_id' => null, // Explicitly set to null
                'comment' => 'Discussion forum has been opened by Legal Admin. All authorized roles may now participate in the discussion.',
                'is_forum_closed' => false,
                'attachment' => null
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error opening discussion forum', [
                'document_id' => $document->id,
                'approver_id' => $approver->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate document number
     */
    private function generateDocumentNumber(DocumentRequest $document): string
    {
        $id = DocumentRequest::latest()->pluck('id')->first();
        $partNumber = is_null($id) ? 1 : ($id + 1);
        
        $divisi = $document->divisi ?? 'GENERAL';
        $initial = preg_replace('/[^A-Z]/', '', strtoupper($divisi));
        if (empty($initial)) {
            $initial = strtoupper(substr($divisi, 0, 3));
        }
        
        $seqNumber = str_pad($partNumber, 4, '0', STR_PAD_LEFT);
        $month = $this->getRomawi(date('n'));
        
        return $seqNumber . "/LRF/" . $initial . "/" . $month . "/" . date("Y");
    }

    /**
     * Get roman numeral for month
     */
    private function getRomawi($bln): string
    {
        $romawi = [
            1 => "I", 2 => "II", 3 => "III", 4 => "IV", 5 => "V", 6 => "VI",
            7 => "VII", 8 => "VIII", 9 => "IX", 10 => "X", 11 => "XI", 12 => "XII"
        ];
        
        return $romawi[$bln] ?? "I";
    }

    /**
     * Get workflow history for document
     */
    public function getWorkflowHistory(DocumentRequest $documentRequest): array
    {
        $history = collect();

        // Add submission
        $history->push([
            'action' => 'submitted',
            'user_name' => $documentRequest->nama,
            'user_role' => 'requester',
            'timestamp' => $documentRequest->submitted_at ?? $documentRequest->created_at,
            'notes' => 'Document request submitted',
        ]);

        // Add approvals
        foreach ($documentRequest->approvals()->orderBy('order')->orderBy('created_at')->get() as $approval) {
            $history->push([
                'action' => $approval->status,
                'user_name' => $approval->approver_name,
                'user_role' => $approval->approval_type,
                'timestamp' => $approval->approved_at ?? $approval->created_at,
                'notes' => $approval->comments ?? ($approval->status === 'pending' ? 'Waiting for approval' : 'Approved'),
            ]);
        }

        // Add discussion start if in discussion
        if ($documentRequest->status === DocumentRequest::STATUS_IN_DISCUSSION) {
            $firstComment = $documentRequest->comments()->first();
            if ($firstComment) {
                $history->push([
                    'action' => 'discussion_started',
                    'user_name' => $firstComment->user_name,
                    'user_role' => $firstComment->user_role,
                    'timestamp' => $firstComment->created_at,
                    'notes' => 'Discussion forum opened',
                ]);
            }
        }

        return $history->sortBy('timestamp')->values()->toArray();
    }

    /**
     * Get current pending approver
     */
    public function getCurrentApprover(DocumentRequest $documentRequest): ?User
    {
        $approval = $documentRequest->approvals()
            ->where('status', DocumentApproval::STATUS_PENDING)
            ->orderBy('order')
            ->first();
            
        if (!$approval) {
            return null;
        }
        
        return User::where('nik', $approval->approver_nik)->first();
    }

    /**
     * Reset workflow (for testing purposes)
     */
    public function resetWorkflow(DocumentRequest $documentRequest): bool
    {
        try {
            DB::beginTransaction();

            // Delete all approvals
            $documentRequest->approvals()->delete();
            
            // Reset document status
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_DRAFT,
                'is_draft' => true,
                'submitted_at' => null,
                'completed_at' => null,
            ]);

            DB::commit();
            
            Log::info("Workflow reset for document {$documentRequest->id}");
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error resetting workflow for document {$documentRequest->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit Agreement Overview for approval (NEW)
     */
    public function submitAgreementOverview(\App\Models\AgreementOverview $agreementOverview): void
    {
        Log::info('Starting AO submit process', [
            'ao_id' => $agreementOverview->id,
            'current_status' => $agreementOverview->status,
            'is_draft' => $agreementOverview->is_draft
        ]);

        DB::transaction(function () use ($agreementOverview) {
            // Update agreement overview status
            $agreementOverview->update([
                'is_draft' => false,
                'status' => \App\Models\AgreementOverview::STATUS_PENDING_HEAD,
                'submitted_at' => now(),
            ]);

            Log::info('Updated AO status', [
                'ao_id' => $agreementOverview->id,
                'new_status' => $agreementOverview->status
            ]);

            // Create first approval record for Head
            try {
                $this->createAgreementApprovalRecord($agreementOverview, 'head');
                Log::info('Successfully created approval record for head');
            } catch (\Exception $e) {
                Log::error('Failed to create approval record', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            Log::info('Agreement overview submitted for approval', [
                'agreement_overview_id' => $agreementOverview->id,
                'nomor_dokumen' => $agreementOverview->nomor_dokumen,
                'status' => $agreementOverview->status,
                'submitted_by' => $agreementOverview->nama
            ]);
        });
    }

    /**
     * Approve Agreement Overview (NEW)
     */
    public function approveAgreementOverview(\App\Models\AgreementOverview $agreementOverview, User $approver, ?string $comments = null): void
    {
        DB::transaction(function () use ($agreementOverview, $approver, $comments) {
            // Update current approval record to approved
            $currentApproval = \App\Models\AgreementApproval::where('agreement_overview_id', $agreementOverview->id)
                ->where('approver_nik', $approver->nik)
                ->where('status', 'pending')
                ->first();

            if ($currentApproval) {
                $currentApproval->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'comments' => $comments,
                ]);
            }

            // Determine next status based on current status
            $nextStatus = $this->getNextAgreementOverviewStatus($agreementOverview->status, $agreementOverview);

            // If Director1 and Director2 are the same person, skip Director2
            if ($agreementOverview->status === AgreementOverview::STATUS_PENDING_DIRECTOR1) {
                if ($approver->nik === $agreementOverview->nik_direksi) {
                    $nextStatus = AgreementOverview::STATUS_APPROVED;
                }
            }
            
            $agreementOverview->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);

            // If not fully approved, create next approval record
            if ($nextStatus !== \App\Models\AgreementOverview::STATUS_APPROVED
                && $nextStatus !== \App\Models\AgreementOverview::STATUS_REDISCUSS
                && $nextStatus !== \App\Models\AgreementOverview::STATUS_REJECTED)
            {
                $nextApprovalType = $this->getNextAgreementApprovalType($nextStatus);
                $this->createAgreementApprovalRecord($agreementOverview, $nextApprovalType);
            } else if ($nextStatus === \App\Models\AgreementOverview::STATUS_APPROVED) {
                // If fully approved, set completion timestamp
                $agreementOverview->update(['completed_at' => now()]);
            }

            Log::info('Agreement overview approved', [
                'agreement_overview_id' => $agreementOverview->id,
                'approver' => $approver->name,
                'approver_role' => $approver->role,
                'previous_status' => $agreementOverview->status,
                'new_status' => $nextStatus,
                'comments' => $comments
            ]);
        });
    }

    /**
     * Reject Agreement Overview (NEW)
     */
    public function rejectAgreementOverview(\App\Models\AgreementOverview $agreementOverview, User $approver, string $comments): void
    {
        DB::transaction(function () use ($agreementOverview, $approver, $comments) {

            // Kalau posisi reject ada di Director1 / Director2 → harus REDISCUSS
            if (in_array($agreementOverview->status, [
                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1,
                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2,
            ])) {
                $agreementOverview->update([
                    'status' => \App\Models\AgreementOverview::STATUS_REDISCUSS,
                    'updated_at' => now(),
                ]);

                Log::info('Agreement overview sent to rediscussion (reject from Director)', [
                    'agreement_overview_id' => $agreementOverview->id,
                    'rejected_by' => $approver->name,
                    'role' => $approver->role,
                    'comments' => $comments
                ]);
            } else {
                // For other roles, direct reject
                $agreementOverview->update([
                    'status' => \App\Models\AgreementOverview::STATUS_REJECTED,
                    'updated_at' => now(),
                ]);

                Log::info('Agreement overview rejected', [
                    'agreement_overview_id' => $agreementOverview->id,
                    'rejected_by' => $approver->name,
                    'role' => $approver->role,
                    'comments' => $comments
                ]);
            }
        });
    }

    /**
     * Send Agreement Overview back for rediscussion (NEW)
     */
    public function sendAgreementOverviewToRediscussion(\App\Models\AgreementOverview $agreementOverview, User $approver, string $comments): void
    {
        DB::transaction(function () use ($agreementOverview, $approver, $comments) {
            $agreementOverview->update([
                'status' => \App\Models\AgreementOverview::STATUS_REDISCUSS,
                'updated_at' => now(),
            ]);

            Log::info('Agreement overview sent to rediscussion', [
                'agreement_overview_id' => $agreementOverview->id,
                'sent_by' => $approver->name,
                'rediscussion_reason' => $comments
            ]);
        });
    }

    // ========== HELPER METHODS FOR AGREEMENT OVERVIEW ==========

    /**
     * Create approval record for Agreement Overview
     */
    private function createAgreementApprovalRecord(\App\Models\AgreementOverview $agreementOverview, string $approvalType, ?string $previousApprovalType = null): void
    {
        // Get approver based on approval type
        $approver = $this->getAgreementApprover($agreementOverview, $approvalType);
        
        if (!$approver) {
            Log::warning('No approver found for approval type', [
                'approval_type' => $approvalType,
                'ao_id' => $agreementOverview->id
            ]);
            return;
        }

        // Map to actual approval type constants from model
        $actualApprovalType = $this->mapToActualApprovalType($approvalType);
        
        \App\Models\AgreementApproval::create([
            'agreement_overview_id' => $agreementOverview->id,
            'approver_nik' => $approver->nik,
            'approver_name' => $approver->name,
            'approval_type' => $actualApprovalType,
            'division_level' => $this->getApprovalDivisionLevel($approvalType),
            'is_division_approval' => $this->isDivisionApprovalType($actualApprovalType),
            'status' => \App\Models\AgreementApproval::STATUS_PENDING,
            'order' => $this->getApprovalOrder($actualApprovalType, $agreementOverview->id),
        ]);

        Log::info('Created approval record', [
            'ao_id' => $agreementOverview->id,
            'approval_type' => $actualApprovalType,
            'approver_nik' => $approver->nik,
            'approver_name' => $approver->name
        ]);
    }

    /**
     * Map simplified approval type to actual model constants
     */
    private function mapToActualApprovalType(string $simpleType): string
    {
        return match ($simpleType) {
            'head'           => \App\Models\AgreementApproval::TYPE_MANAGER, // bisa manager / senior_manager
            'senior_manager' => \App\Models\AgreementApproval::TYPE_SENIOR_MANAGER,
            'general_manager'=> \App\Models\AgreementApproval::TYPE_GENERAL_MANAGER,
            'finance'        => \App\Models\AgreementApproval::TYPE_HEAD_FINANCE,
            'legal_admin'    => \App\Models\AgreementApproval::TYPE_HEAD_LEGAL,
            'legal'          => \App\Models\AgreementApproval::TYPE_LEGAL,
            'director'       => \App\Models\AgreementApproval::TYPE_SELECTED_DIRECTOR,
            default          => $simpleType
        };
    }

    /**
     * Get approver user based on approval type and AO
     */
    private function getAgreementApprover(\App\Models\AgreementOverview $agreementOverview, string $approvalType): ?User
    {
        return match ($approvalType) {
            'head' => User::where('role', 'head')
                         ->where('divisi', $agreementOverview->divisi)
                         ->first(),
            'general_manager' => User::where('role', 'general_manager')->first(),
            'finance' => User::whereIn('role', ['finance', 'head_finance'])->first(),
            'legal_admin' => User::whereIn('role', ['legal', 'head_legal'])->first(),
            'director1' => User::where('nik', $agreementOverview->nik_direksi)->first(),
            'director2' => User::where('role', 'director')
                              ->where('nik', '!=', $agreementOverview->nik_direksi)
                              ->first(),
            default => null
        };
    }

    /**
     * Get next approval type based on status
     */
    private function getNextAgreementApprovalType(string $status): string
    {
        return match ($status) {
            \App\Models\AgreementOverview::STATUS_PENDING_GM => 'general_manager',
            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE => 'finance',
            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL => 'legal_admin',
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1,
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'selected_director',
            default => 'head'
        };
    }

    /**
     * Get division level for approval type
     */
    private function getApprovalDivisionLevel(string $approvalType): ?string
    {
        return match ($approvalType) {
            'head' => 'division',
            'general_manager' => 'company',
            'finance' => 'company',
            'legal_admin' => 'company',
            'director1', 'director2' => 'board',
            default => null
        };
    }

    /**
     * Check if approval type is division approval
     */
    private function isDivisionApprovalType(string $approvalType): bool
    {
        return in_array($approvalType, [
            \App\Models\AgreementApproval::TYPE_DIVISION_MANAGER,
            \App\Models\AgreementApproval::TYPE_DIVISION_SENIOR_MANAGER,
            \App\Models\AgreementApproval::TYPE_DIVISION_GENERAL_MANAGER,
        ]);
    }

    /**
     * Get approval order using model constants
     */
    private function getApprovalOrder(string $approvalType, int $agreementOverviewId): int
    {
        // Ambil semua approved records
        $approvedTypes = \App\Models\AgreementApproval::where('agreement_overview_id', $agreementOverviewId)
            ->where('status', \App\Models\AgreementApproval::STATUS_APPROVED)
            ->pluck('approval_type')
            ->toArray();

        $orders = \App\Models\AgreementApproval::getApprovalOrder();

        // Rule: kalau Senior Manager sudah approve → hapus GM dari list
        if (in_array(\App\Models\AgreementApproval::TYPE_SENIOR_MANAGER, $approvedTypes)) {
            unset($orders[\App\Models\AgreementApproval::TYPE_GENERAL_MANAGER]);
        }

        return $orders[$approvalType] ?? 0;
    }

    /**
     * Get next status in Agreement Overview workflow
     */
    private function getNextAgreementOverviewStatus(string $currentStatus, \App\Models\AgreementOverview $ao): string
    {
        // Jika Senior Manager sudah approve → langsung ke Finance
        if ($currentStatus === \App\Models\AgreementOverview::STATUS_PENDING_HEAD) {
            $approvedTypes = \App\Models\AgreementApproval::where('agreement_overview_id', $ao->id)
                ->where('status', \App\Models\AgreementApproval::STATUS_APPROVED)
                ->pluck('approval_type')
                ->toArray();

            if (in_array(\App\Models\AgreementApproval::TYPE_SENIOR_MANAGER, $approvedTypes)) {
                return \App\Models\AgreementOverview::STATUS_PENDING_FINANCE;
            }

            return \App\Models\AgreementOverview::STATUS_PENDING_GM;
        }

        return match ($currentStatus) {
            \App\Models\AgreementOverview::STATUS_PENDING_GM => \App\Models\AgreementOverview::STATUS_PENDING_FINANCE,
            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE => \App\Models\AgreementOverview::STATUS_PENDING_LEGAL,
            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL => \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1,
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2,
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => \App\Models\AgreementOverview::STATUS_APPROVED,
            default => $currentStatus
        };
    }

    /**
     * Check if user can approve Agreement Overview at current status
     */
    public function canUserApproveAgreementOverview(User $user, AgreementOverview $agreementOverview): bool
    {
        // Check role permission based on current status
        $rolePermission = match ($agreementOverview->status) {
            AgreementOverview::STATUS_PENDING_HEAD =>
                in_array($user->role, ['head', 'manager', 'senior_manager']),
            AgreementOverview::STATUS_PENDING_GM =>
                in_array($user->role, ['senior_manager', 'general_manager']),
            AgreementOverview::STATUS_PENDING_FINANCE =>
                $user->role === 'head_finance',
            AgreementOverview::STATUS_PENDING_LEGAL =>
                $user->role === 'head_legal',
            AgreementOverview::STATUS_PENDING_DIRECTOR1 =>
                $user->role === 'director' && $user->nik === $agreementOverview->nik_direksi,
            AgreementOverview::STATUS_PENDING_DIRECTOR2 =>
                $user->role === 'director' && $user->nik !== $agreementOverview->nik_direksi,
            default => false,
        };

        // Creatore cannot approve their own AO
        $isCreator = (string) $agreementOverview->nik === (string) $user->nik;

        if (!$rolePermission) {
            Log::info('Permission denied: role tidak sesuai', [
                'user_nik'  => $user->nik,
                'user_role' => $user->role,
                'ao_status' => $agreementOverview->status,
                'ao_id'     => $agreementOverview->id,
            ]);
            return false;
        }

        // Find pending approval record for this user
        $pendingApproval = \App\Models\AgreementApproval::where([
            'agreement_overview_id' => $agreementOverview->id,
            'approver_nik'          => $user->nik,
            'status'                => 'pending',
        ])->first();

        Log::info('Permission check result', [
            'user_nik'        => $user->nik,
            'user_role'       => $user->role,
            'ao_status'       => $agreementOverview->status,
            'ao_id'           => $agreementOverview->id,
            'role_permission' => $rolePermission,
            'has_pending'     => $pendingApproval !== null,
            'is_creator'      => $agreementOverview->nik === (string) $user->nik,
            'final_result'    => $pendingApproval !== null && $rolePermission,
        ]);

        return $pendingApproval !== null && $rolePermission && $isCreator === false;
    }

    /**
     * Get Agreement Overview workflow progress percentage
     */
    public function getAgreementOverviewProgress(\App\Models\AgreementOverview $agreementOverview): int
    {
        return match ($agreementOverview->status) {
            \App\Models\AgreementOverview::STATUS_DRAFT => 0,
            \App\Models\AgreementOverview::STATUS_PENDING_HEAD => 10,
            \App\Models\AgreementOverview::STATUS_PENDING_GM => 25,
            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE => 40,
            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL => 60,
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 75,
            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 90,
            \App\Models\AgreementOverview::STATUS_APPROVED => 100,
            \App\Models\AgreementOverview::STATUS_REJECTED => 0,
            \App\Models\AgreementOverview::STATUS_REDISCUSS => 50,
            default => 0
        };
    }
}
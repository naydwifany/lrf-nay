<?php
// app/Services/DocumentRequestService.php (CORRECTED FLOW)

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentRequestService
{
    protected $discussionService;

    public function __construct()
    {
        $this->discussionService = app(DocumentDiscussionService::class);
    }

    /**
     * Create new document request
     */
    public function create(User $user, array $data): DocumentRequest
    {
        try {
            DB::beginTransaction();

            $documentRequest = DocumentRequest::create([
                'user_id' => $user->id,
                'nik' => $user->nik,
                'nik_atasan' => $user->supervisor_nik,
                'nama' => $user->name,
                'jabatan' => $user->jabatan,
                'divisi' => $user->divisi,
                'unit_bisnis' => $user->unit_name,
                'dept' => $user->department,
                'direktorat' => $user->direktorat,
                'status' => DocumentRequest::STATUS_DRAFT,
                'is_draft' => true,
                ...$data
            ]);

            DB::commit();
            return $documentRequest;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create document request: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit document request - CORRECTED FLOW
     * RULE: draft -> submit = pending_supervisor
     */
    public function submit(DocumentRequest $documentRequest): bool
    {
        try {
            DB::beginTransaction();

            $user = $documentRequest->user;
            
            // Update document status - ALWAYS start with supervisor
            $documentRequest->update([
                'is_draft' => false,
                'status' => DocumentRequest::STATUS_PENDING_SUPERVISOR,
                'submitted_at' => now()
            ]);

            // Create simple approval flow - ONLY supervisor first
            $this->createSupervisorApproval($documentRequest);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit document request: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create supervisor approval only
     */
    protected function createSupervisorApproval(DocumentRequest $documentRequest): void
    {
        $user = $documentRequest->user;

        Log::info('Creating supervisor approval', [
            'document_id' => $documentRequest->id,
            'user_nik' => $user->nik,
            'supervisor_nik' => $user->supervisor_nik
        ]);

        // ONLY create supervisor approval
        if ($user->supervisor_nik) {
            $supervisor = User::where('nik', $user->supervisor_nik)->first();
            
            DocumentApproval::create([
                'document_request_id' => $documentRequest->id,
                'approver_nik' => $user->supervisor_nik,
                'approver_name' => $supervisor ? $supervisor->name : 'Atasan Langsung',
                'approval_type' => 'supervisor',
                'status' => DocumentApproval::STATUS_PENDING,
                'order' => 1
            ]);

            Log::info('Created supervisor approval', [
                'supervisor_nik' => $user->supervisor_nik,
                'supervisor_name' => $supervisor?->name,
                'supervisor_role' => $supervisor?->role,
                'supervisor_jabatan' => $supervisor?->jabatan
            ]);
        }
    }

    /**
     * Process approval action - CORRECTED LOGIC
     */
    public function processApproval(
        DocumentApproval $approval, 
        string $action, 
        string $comments = null, 
        User $approver = null
    ): bool {
        try {
            DB::beginTransaction();

            $documentRequest = $approval->documentRequest;

            Log::info('Processing approval', [
                'document_id' => $documentRequest->id,
                'approval_id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'action' => $action,
                'approver_nik' => $approver?->nik,
                'approver_role' => $approver?->role,
                'approver_jabatan' => $approver?->jabatan
            ]);

            if ($action === 'approve') {
                // Approve current step
                $approval->update([
                    'status' => DocumentApproval::STATUS_APPROVED,
                    'approved_at' => now(),
                    'comments' => $comments,
                    'approved_by' => $approver ? $approver->nik : null
                ]);

                // RULE-BASED: Determine next step based on approval type and approver level
                $this->determineNextStep($documentRequest, $approval, $approver);

            } else {
                // Reject
                $approval->update([
                    'status' => DocumentApproval::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'comments' => $comments,
                    'rejected_by' => $approver ? $approver->nik : null
                ]);

                $documentRequest->update([
                    'status' => DocumentRequest::STATUS_REJECTED
                ]);

                Log::info('Document rejected', [
                    'document_id' => $documentRequest->id,
                    'rejected_by' => $approver?->nik,
                    'reason' => $comments
                ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process approval: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine next step based on CORRECT RULES
     */
    protected function determineNextStep(DocumentRequest $documentRequest, DocumentApproval $currentApproval, User $approver): void
    {
        if ($currentApproval->approval_type === 'supervisor') {
            // RULE: supervisor approved, check supervisor level
            if ($this->isSeniorManagerLevel($approver)) {
                // Senior Manager → directly to Legal Admin
                $this->createLegalAdminApproval($documentRequest);
                $documentRequest->update(['status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN]);
                
                Log::info('Supervisor is Senior Manager, going directly to Legal Admin', [
                    'document_id' => $documentRequest->id,
                    'approver_level' => 'senior_manager'
                ]);
            } else {
                // Regular Manager → need GM approval (but we don't create it yet based on your flow)
                // According to your rule, it should go directly to legal admin?
                // Let me clarify: if supervisor is NOT senior manager, where does it go?
                
                // For now, following your rule: supervisor → legal_admin
                $this->createLegalAdminApproval($documentRequest);
                $documentRequest->update(['status' => DocumentRequest::STATUS_PENDING_LEGAL_ADMIN]);
                
                Log::info('Regular supervisor approved, going to Legal Admin', [
                    'document_id' => $documentRequest->id,
                    'approver_level' => 'regular_supervisor'
                ]);
            }
            
        } elseif ($currentApproval->approval_type === 'admin_legal') {
            // RULE: Legal Admin approved → open discussion
            $this->openDiscussion($documentRequest);
            
            Log::info('Legal Admin approved, opening discussion', [
                'document_id' => $documentRequest->id
            ]);
        }
    }

    /**
     * Check if user is Senior Manager level
     */
    protected function isSeniorManagerLevel(User $user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');

        // Senior Manager indicators
        $seniorManagerKeywords = [
            'senior_manager', 'senior manager',
            'general_manager', 'general manager', 'gm',
            'head_manager', 'head manager',
            'kepala divisi', 'kadiv',
            'director', 'direktur'
        ];

        foreach ($seniorManagerKeywords as $keyword) {
            if (str_contains($role, $keyword) || str_contains($jabatan, $keyword)) {
                Log::info('User identified as Senior Manager level', [
                    'user_nik' => $user->nik,
                    'role' => $role,
                    'jabatan' => $jabatan,
                    'matched_keyword' => $keyword
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Create Legal Admin approval
     */
    protected function createLegalAdminApproval(DocumentRequest $documentRequest): void
    {
        $adminLegal = User::where(function($query) {
                $query->where('role', 'admin_legal')
                      ->orWhere('role', 'legal_admin')
                      ->orWhere('role', 'legal');
            })
            ->where('is_active', true)
            ->first();

        if (!$adminLegal) {
            // Fallback: any legal role
            $adminLegal = User::where(function($query) {
                    $query->where('role', 'like', '%legal%')
                          ->orWhere('jabatan', 'like', '%legal%')
                          ->orWhere('jabatan', 'like', '%hukum%');
                })
                ->where('is_active', true)
                ->first();
        }

        $nextOrder = $documentRequest->approvals()->max('order') + 1;

        DocumentApproval::create([
            'document_request_id' => $documentRequest->id,
            'approver_nik' => $adminLegal ? $adminLegal->nik : 'LEGAL_DEFAULT',
            'approver_name' => $adminLegal ? $adminLegal->name : 'Admin Legal',
            'approval_type' => 'admin_legal',
            'status' => DocumentApproval::STATUS_PENDING,
            'order' => $nextOrder
        ]);

        Log::info('Created Legal Admin approval', [
            'legal_nik' => $adminLegal?->nik,
            'legal_name' => $adminLegal?->name,
            'order' => $nextOrder
        ]);
    }

    /**
     * Open discussion forum
     * RULE: Yang bisa participate: manager divisi, senior manager divisi, finance, head finance, legal, head_legal
     */
    public function openDiscussion(DocumentRequest $documentRequest): bool
    {
        try {
            // Update status to discussion
            $documentRequest->update([
                'status' => 'in_discussion'
            ]);

            // Notify participants who can join discussion
            $this->notifyDiscussionParticipants($documentRequest);

            Log::info('Discussion opened for document', [
                'document_id' => $documentRequest->id,
                'title' => $documentRequest->title,
                'requester' => $documentRequest->nama
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to open discussion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify discussion participants
     */
    protected function notifyDiscussionParticipants(DocumentRequest $documentRequest): void
    {
        // Get users who can participate in discussion
        $participants = User::where(function($query) use ($documentRequest) {
                // Manager/Senior Manager dari divisi yang sama
                $query->where(function($q) use ($documentRequest) {
                    $q->where('divisi', $documentRequest->divisi)
                      ->whereIn('role', ['manager', 'senior_manager', 'head_manager']);
                })
                // OR Finance roles (any division)
                ->orWhereIn('role', ['finance', 'head_finance'])
                // OR Legal roles (any division)  
                ->orWhereIn('role', ['legal', 'admin_legal', 'head_legal', 'legal_admin']);
            })
            ->where('is_active', true)
            ->get();

        Log::info('Discussion participants notified', [
            'document_id' => $documentRequest->id,
            'participant_count' => $participants->count(),
            'participants' => $participants->pluck('nik', 'name')->toArray()
        ]);

        // Here you can add actual notification logic
        foreach ($participants as $participant) {
            // Send notification to each participant
            Log::info('Notifying discussion participant', [
                'participant_nik' => $participant->nik,
                'participant_name' => $participant->name,
                'participant_role' => $participant->role
            ]);
        }
    }

    /**
     * Close discussion forum - ONLY head_legal can close
     */
    public function closeDiscussion(DocumentRequest $documentRequest, User $user): bool
    {
        try {
            DB::beginTransaction();

            // RULE: Only head legal can close discussion
            if (!$this->isHeadLegal($user)) {
                throw new \Exception('Only Head Legal can close discussion');
            }

            // RULE: After discussion closed → can create AO
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_AGREEMENT_CREATION
            ]);

            Log::info('Discussion closed by Head Legal', [
                'document_id' => $documentRequest->id,
                'closed_by' => $user->nik,
                'new_status' => 'agreement_creation'
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close discussion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is Head Legal
     */
    protected function isHeadLegal(User $user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');

        return str_contains($role, 'head_legal') || 
               str_contains($jabatan, 'head legal') ||
               str_contains($jabatan, 'kepala legal');
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovalsForUser(User $user): array
    {
        $documentApprovals = DocumentApproval::where('approver_nik', $user->nik)
            ->where('status', DocumentApproval::STATUS_PENDING)
            ->with(['documentRequest' => function($query) {
                $query->with(['user']);
            }])
            ->get();

        return [
            'approvals' => $documentApprovals,
            'count' => $documentApprovals->count()
        ];
    }

    /**
     * Get approval flow preview - CORRECTED
     */
    public function getApprovalFlowPreview(User $user): array
    {
        $approvers = [];
        
        // 1. Supervisor (ALWAYS first)
        if ($user->supervisor_nik) {
            $supervisor = User::where('nik', $user->supervisor_nik)->first();
            $approvers[] = $supervisor ? $supervisor->name . ' (Supervisor)' : 'Atasan Langsung';
        }
        
        // 2. Legal Admin (ALWAYS after supervisor)
        $approvers[] = 'Legal Admin';

        // 3. Discussion Phase
        $approvers[] = 'Discussion Phase (Multiple participants)';

        // 4. Head Legal (Close discussion)
        $approvers[] = 'Head Legal (Close discussion)';

        return [
            'approvers' => $approvers,
            'total_steps' => count($approvers),
            'estimated_days' => count($approvers) * 2,
            'note' => 'Flow may vary based on supervisor level'
        ];
    }

    /**
     * Get document statistics for dashboard
     */
    public function getDocumentStatistics(User $user): array
    {
        $stats = [];

        if ($this->isLegalRole($user)) {
            $stats = [
                'total_documents' => DocumentRequest::where('is_draft', false)->count(),
                'pending_approval' => DocumentRequest::whereIn('status', [
                    DocumentRequest::STATUS_PENDING_SUPERVISOR,
                    DocumentRequest::STATUS_PENDING_LEGAL_ADMIN
                ])->count(),
                'in_discussion' => DocumentRequest::where('status', 'in_discussion')->count(),
                'can_create_ao' => DocumentRequest::where('status', 'agreement_creation')->count(),
                'my_pending_approvals' => $this->getPendingApprovalsForUser($user)['count']
            ];
        } elseif (in_array($user->role, ['finance', 'head_finance'])) {
            $stats = [
                'discussions_available' => DocumentRequest::where('status', 'in_discussion')->count(),
                'my_pending_approvals' => $this->getPendingApprovalsForUser($user)['count'],
            ];
        } else {
            $stats = [
                'my_documents' => DocumentRequest::where('user_id', $user->id)->where('is_draft', false)->count(),
                'my_drafts' => DocumentRequest::where('user_id', $user->id)->where('is_draft', true)->count(),
                'can_create_ao' => DocumentRequest::where('nik', $user->nik)->where('status', 'agreement_creation')->count(),
                'my_pending_approvals' => $this->getPendingApprovalsForUser($user)['count'],
            ];
        }

        return $stats;
    }

    /**
     * Check if user has legal role
     */
    protected function isLegalRole(User $user): bool
    {
        $role = strtolower($user->role ?? '');
        return str_contains($role, 'legal') || str_contains($role, 'hukum');
    }
}
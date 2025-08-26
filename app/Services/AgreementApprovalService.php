<?php
// app/Services/AgreementApprovalService.php (CORRECTED AO FLOW)

namespace App\Services;

use App\Models\AgreementOverview;
use App\Models\AgreementApproval;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class AgreementApprovalService
{
    /**
     * Create approval workflow for AO based on CORRECT RULES
     * RULE: user create ao -> pending_supervisor -> pending_finance -> pending_head_legal -> director1 -> director2
     */
    public function createApprovalWorkflow(AgreementOverview $agreementOverview): void
    {
        try {
            Log::info('Creating AO approval workflow', [
                'ao_id' => $agreementOverview->id,
                'requester_nik' => $agreementOverview->nik,
                'requester_jabatan' => $agreementOverview->jabatan
            ]);

            // Clear existing approvals if any
            AgreementApproval::where('agreement_overview_id', $agreementOverview->id)->delete();
            
            // FIXED FLOW: supervisor -> finance -> head_legal -> director1 -> director2
            $approvalFlow = $this->getCorrectApprovalFlow($agreementOverview);
            
            Log::info('AO approval flow determined', [
                'ao_id' => $agreementOverview->id,
                'flow_count' => count($approvalFlow)
            ]);
            
            // Create approval records
            foreach ($approvalFlow as $index => $approval) {
                $this->createApprovalRecord($agreementOverview, $approval, $index + 1);
            }
            
            // Set first approval as pending and update AO status
            $this->setNextApprovalPending($agreementOverview);
            
            Log::info('AO approval workflow created successfully', [
                'ao_id' => $agreementOverview->id,
                'total_approvals' => count($approvalFlow)
            ]);
            
        } catch (Exception $e) {
            Log::error('Error creating AO approval workflow', [
                'ao_id' => $agreementOverview->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get correct approval flow for AO
     * RULE: supervisor -> finance/head_finance -> head_legal -> director1 -> director2
     */
    private function getCorrectApprovalFlow(AgreementOverview $agreementOverview): array
    {
        return [
            ['type' => AgreementApproval::TYPE_SUPERVISOR],      // 1. Supervisor
            ['type' => AgreementApproval::TYPE_HEAD_FINANCE],    // 2. Finance/Head Finance
            ['type' => AgreementApproval::TYPE_HEAD_LEGAL],      // 3. Head Legal
            ['type' => AgreementApproval::TYPE_DIRECTOR_SUPERVISOR], // 4. Director 1
            ['type' => AgreementApproval::TYPE_SELECTED_DIRECTOR],   // 5. Director 2
        ];
    }
    
    /**
     * Create individual approval record
     */
    private function createApprovalRecord(AgreementOverview $agreementOverview, array $approval, int $order): void
    {
        // Get approver based on type and agreement data
        $approver = $this->getApprover($agreementOverview, $approval['type']);
        
        if (!$approver) {
            Log::warning('No approver found for AO approval type', [
                'ao_id' => $agreementOverview->id,
                'approval_type' => $approval['type']
            ]);
            return;
        }
        
        AgreementApproval::create([
            'agreement_overview_id' => $agreementOverview->id,
            'approver_nik' => $approver['nik'],
            'approver_name' => $approver['name'],
            'approval_type' => $approval['type'],
            'status' => AgreementApproval::STATUS_PENDING,
            'order' => $order,
            'division_level' => $approval['type'],
            'is_division_approval' => false,
        ]);
        
        Log::info('AO approval record created', [
            'ao_id' => $agreementOverview->id,
            'approver_nik' => $approver['nik'],
            'approval_type' => $approval['type'],
            'order' => $order
        ]);
    }
    
    /**
     * Get approver for specific approval type
     */
    private function getApprover(AgreementOverview $agreementOverview, string $approvalType): ?array
    {
        switch ($approvalType) {
            case AgreementApproval::TYPE_SUPERVISOR:
                // Get supervisor of AO creator
                $creator = User::where('nik', $agreementOverview->nik)->first();
                if ($creator && $creator->supervisor_nik) {
                    $supervisor = User::where('nik', $creator->supervisor_nik)->first();
                    if ($supervisor) {
                        return [
                            'nik' => $supervisor->nik,
                            'name' => $supervisor->name
                        ];
                    }
                }
                // Fallback
                return ['nik' => 'SUPERVISOR_DEFAULT', 'name' => 'Supervisor'];
                
            case AgreementApproval::TYPE_HEAD_FINANCE:
                // Find Head Finance
                $headFinance = User::where(function($query) {
                        $query->where('role', 'head_finance')
                              ->orWhere('role', 'finance_head')
                              ->orWhere('jabatan', 'like', '%head finance%')
                              ->orWhere('jabatan', 'like', '%kepala keuangan%');
                    })
                    ->where('is_active', true)
                    ->first();
                    
                if (!$headFinance) {
                    // Fallback to any finance role
                    $headFinance = User::where('role', 'finance')
                        ->where('is_active', true)
                        ->first();
                }
                
                return $headFinance ? [
                    'nik' => $headFinance->nik,
                    'name' => $headFinance->name
                ] : ['nik' => 'FINANCE_HEAD', 'name' => 'Head Finance'];
                
            case AgreementApproval::TYPE_HEAD_LEGAL:
                // Find Head Legal
                $headLegal = User::where(function($query) {
                        $query->where('role', 'head_legal')
                              ->orWhere('role', 'legal_head')
                              ->orWhere('jabatan', 'like', '%head legal%')
                              ->orWhere('jabatan', 'like', '%kepala hukum%');
                    })
                    ->where('is_active', true)
                    ->first();
                    
                return $headLegal ? [
                    'nik' => $headLegal->nik,
                    'name' => $headLegal->name
                ] : ['nik' => 'LEGAL_HEAD', 'name' => 'Head Legal'];
                
            case AgreementApproval::TYPE_DIRECTOR_SUPERVISOR:
                // Director 1 - from AO data or auto-assigned
                $director1Nik = $agreementOverview->director1_nik ?? 
                               $agreementOverview->nama_direksi_default ?? 
                               'DIR1_001';
                               
                $director1Name = $agreementOverview->director1_name ?? 
                                $agreementOverview->nama_direksi_default ?? 
                                'Director 1';
                                
                return [
                    'nik' => $director1Nik,
                    'name' => $director1Name
                ];
                
            case AgreementApproval::TYPE_SELECTED_DIRECTOR:
                // Director 2 - from AO data (user selected)
                $director2Nik = $agreementOverview->director2_nik ?? 
                               $agreementOverview->nik_direksi ?? 
                               'DIR2_001';
                               
                // Try to get director name from User table
                $director2 = User::where('nik', $director2Nik)->first();
                $director2Name = $director2 ? $director2->name : 
                                ($agreementOverview->director2_name ?? 'Director 2');
                                
                return [
                    'nik' => $director2Nik,
                    'name' => $director2Name
                ];
                
            default:
                return null;
        }
    }
    
    /**
     * Set next approval as pending and update AO status
     */
    private function setNextApprovalPending(AgreementOverview $agreementOverview): void
    {
        $nextApproval = $agreementOverview->approvals()
            ->where('status', AgreementApproval::STATUS_PENDING)
            ->orderBy('order')
            ->first();
            
        if ($nextApproval) {
            // Update AO status based on next approval type
            $status = $this->getAOStatusFromApprovalType($nextApproval->approval_type);
            $agreementOverview->update(['status' => $status]);
            
            Log::info('Next AO approval set as pending', [
                'ao_id' => $agreementOverview->id,
                'approver_nik' => $nextApproval->approver_nik,
                'approval_type' => $nextApproval->approval_type,
                'ao_status' => $status
            ]);
        }
    }
    
    /**
     * Map approval type to AO status
     */
    private function getAOStatusFromApprovalType(string $approvalType): string
    {
        return match($approvalType) {
            AgreementApproval::TYPE_SUPERVISOR => 'pending_supervisor',
            AgreementApproval::TYPE_HEAD_FINANCE => 'pending_finance',
            AgreementApproval::TYPE_HEAD_LEGAL => 'pending_legal',
            AgreementApproval::TYPE_DIRECTOR_SUPERVISOR => 'pending_director1',
            AgreementApproval::TYPE_SELECTED_DIRECTOR => 'pending_director2',
            default => 'pending_supervisor'
        };
    }

    /**
     * Process AO approval
     */
    public function processApproval(
        AgreementApproval $approval, 
        string $action, 
        string $comments = null, 
        User $approver = null
    ): bool {
        try {
            $agreementOverview = $approval->agreementOverview;

            Log::info('Processing AO approval', [
                'ao_id' => $agreementOverview->id,
                'approval_id' => $approval->id,
                'approval_type' => $approval->approval_type,
                'action' => $action,
                'approver_nik' => $approver?->nik
            ]);

            if ($action === 'approve') {
                // Approve current step
                $approval->update([
                    'status' => AgreementApproval::STATUS_APPROVED,
                    'approved_at' => now(),
                    'comments' => $comments,
                    'approved_by' => $approver ? $approver->nik : null
                ]);

                // Move to next approval or complete
                $this->moveToNextAOApproval($agreementOverview);

            } else {
                // Reject
                $approval->update([
                    'status' => AgreementApproval::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'comments' => $comments,
                    'rejected_by' => $approver ? $approver->nik : null
                ]);

                $agreementOverview->update([
                    'status' => AgreementOverview::STATUS_REJECTED
                ]);

                Log::info('AO rejected', [
                    'ao_id' => $agreementOverview->id,
                    'rejected_by' => $approver?->nik,
                    'reason' => $comments
                ]);
            }

            return true;

        } catch (Exception $e) {
            Log::error('Failed to process AO approval: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Move to next AO approval
     */
    private function moveToNextAOApproval(AgreementOverview $agreementOverview): void
    {
        // Get next pending approval
        $nextApproval = $agreementOverview->approvals()
            ->where('status', AgreementApproval::STATUS_PENDING)
            ->orderBy('order')
            ->first();

        if ($nextApproval) {
            // Move to next approval step
            $status = $this->getAOStatusFromApprovalType($nextApproval->approval_type);
            $agreementOverview->update(['status' => $status]);

            Log::info('Moved to next AO approval', [
                'ao_id' => $agreementOverview->id,
                'next_approval_type' => $nextApproval->approval_type,
                'next_approver' => $nextApproval->approver_nik,
                'new_status' => $status
            ]);

        } else {
            // All approvals completed - AO is fully approved
            $agreementOverview->update([
                'status' => AgreementOverview::STATUS_APPROVED,
                'completed_at' => now()
            ]);

            Log::info('All AO approvals completed', [
                'ao_id' => $agreementOverview->id,
                'completed_at' => now()
            ]);
        }
    }

    /**
     * Get pending AO approvals for user
     */
    public function getPendingAOApprovalsForUser(User $user): array
    {
        $aoApprovals = AgreementApproval::where('approver_nik', $user->nik)
            ->where('status', AgreementApproval::STATUS_PENDING)
            ->with(['agreementOverview'])
            ->get();

        return [
            'approvals' => $aoApprovals,
            'count' => $aoApprovals->count()
        ];
    }

    /**
     * Get AO approval flow preview
     */
    public function getAOApprovalFlowPreview(User $user): array
    {
        $approvers = [];
        
        // FIXED FLOW for AO
        // 1. Supervisor
        if ($user->supervisor_nik) {
            $supervisor = User::where('nik', $user->supervisor_nik)->first();
            $approvers[] = $supervisor ? $supervisor->name . ' (Supervisor)' : 'Atasan Langsung';
        } else {
            $approvers[] = 'Supervisor';
        }
        
        // 2. Head Finance
        $approvers[] = 'Head Finance';
        
        // 3. Head Legal
        $approvers[] = 'Head Legal';
        
        // 4. Director 1 (Auto-assigned)
        $approvers[] = 'Director 1 (Auto-assigned)';
        
        // 5. Director 2 (User selected)
        $approvers[] = 'Director 2 (Selected)';

        return [
            'approvers' => $approvers,
            'total_steps' => count($approvers),
            'estimated_days' => count($approvers) * 3, // 3 days per step for AO
            'note' => 'Agreement Overview approval flow is fixed: Supervisor → Finance → Legal → Director 1 → Director 2'
        ];
    }

    /**
     * Get user by role with fallback
     */
    private function getUserByRole(string $role): ?array
    {
        try {
            $user = User::where('role', $role)
                       ->where('is_active', true)
                       ->first();

            if (!$user) {
                Log::warning('User with role not found', ['role' => $role]);
                return $this->getFallbackRoleApprover($role);
            }

            Log::info('Found user by role', [
                'role' => $role,
                'nik' => $user->nik,
                'name' => $user->name
            ]);

            return [
                'nik' => $user->nik,
                'name' => $user->name
            ];

        } catch (Exception $e) {
            Log::error('Error getting user by role', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            
            return $this->getFallbackRoleApprover($role);
        }
    }

    /**
     * Fallback role approver
     */
    private function getFallbackRoleApprover(string $role): array
    {
        $fallbackData = [
            'supervisor' => ['nik' => 'SUPERVISOR_001', 'name' => 'Supervisor'],
            'head_finance' => ['nik' => 'FINANCE_HEAD', 'name' => 'Head Finance'],
            'head_legal' => ['nik' => 'LEGAL_HEAD', 'name' => 'Head Legal'],
            'director' => ['nik' => 'DIRECTOR_001', 'name' => 'Director'],
        ];

        return $fallbackData[$role] ?? ['nik' => 'UNKNOWN_ROLE', 'name' => 'Unknown Role'];
    }
}
<?php
// app/Console/Commands/DebugAOSubmitCommand.php

namespace App\Console\Commands;

use App\Models\AgreementOverview;
use App\Models\AgreementApproval;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Console\Command;

class DebugAOSubmitCommand extends Command
{
    protected $signature = 'ao:debug-submit {id : Agreement Overview ID}';
    protected $description = 'Debug Agreement Overview submit process';

    public function handle()
    {
        $aoId = $this->argument('id');
        
        // Find Agreement Overview
        $ao = AgreementOverview::find($aoId);
        
        if (!$ao) {
            $this->error("Agreement Overview with ID {$aoId} not found!");
            return 1;
        }

        $this->info("=== DEBUGGING AO SUBMIT PROCESS ===");
        $this->info("AO ID: {$ao->id}");
        $this->info("AO Number: {$ao->nomor_dokumen}");
        $this->info("Current Status: {$ao->status}");
        $this->info("Is Draft: " . ($ao->is_draft ? 'Yes' : 'No'));
        $this->info("Division: {$ao->divisi}");
        $this->info("Selected Director NIK: {$ao->nik_direksi}");
        
        $this->newLine();

        // Check if AO can be submitted
        if (!$ao->is_draft || $ao->status !== AgreementOverview::STATUS_DRAFT) {
            $this->error("AO cannot be submitted - not in draft status!");
            return 1;
        }

        // Test finding head approver
        $this->info("=== FINDING HEAD APPROVER ===");
        $headApprover = User::where('role', 'head')
                           ->where('divisi', $ao->divisi)
                           ->first();
        
        if ($headApprover) {
            $this->info("✅ Head approver found: {$headApprover->name} (NIK: {$headApprover->nik})");
        } else {
            $this->error("❌ No head approver found for division: {$ao->divisi}");
            
            // Show available head users
            $this->info("Available head users:");
            $heads = User::where('role', 'head')->get();
            foreach ($heads as $head) {
                $this->line("  - {$head->name} (NIK: {$head->nik}, Division: {$head->divisi})");
            }
        }

        $this->newLine();

        // Test the submit process
        if ($this->confirm('Do you want to test submit this AO?')) {
            try {
                $workflowService = app(DocumentWorkflowService::class);
                $workflowService->submitAgreementOverview($ao);
                
                $this->info("✅ Submit successful!");
                
                // Check approval records
                $approvals = AgreementApproval::where('agreement_overview_id', $ao->id)->get();
                $this->info("Created approval records: " . $approvals->count());
                
                foreach ($approvals as $approval) {
                    $this->line("  - Type: {$approval->approval_type}, Approver: {$approval->approver_name} ({$approval->approver_nik}), Status: {$approval->status}");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Submit failed: " . $e->getMessage());
                $this->error("Trace: " . $e->getTraceAsString());
            }
        }

        return 0;
    }
}
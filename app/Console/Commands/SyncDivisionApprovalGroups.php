<?php
// app/Console/Commands/SyncDivisionApprovalGroups.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DivisionApprovalService;
use App\Models\User;
use App\Models\DivisionApprovalGroup;

class SyncDivisionApprovalGroups extends Command
{
    protected $signature = 'sync:division-approvals {--force : Force sync even if recently synced}';
    protected $description = 'Sync division approval groups from HRIS API';

    protected $divisionApprovalService;

    public function __construct(DivisionApprovalService $divisionApprovalService)
    {
        parent::__construct();
        $this->divisionApprovalService = $divisionApprovalService;
    }

    public function handle()
    {
        $this->info('Starting division approval groups synchronization...');

        try {
            // Get all active users with divisions
            $users = User::where('is_active', true)
                         ->whereNotNull('divisi')
                         ->whereNotNull('api_token')
                         ->get();

            $this->info("Found {$users->count()} users to process");

            $processedDivisions = [];
            $updatedGroups = 0;
            $createdGroups = 0;

            foreach ($users as $user) {
                // Skip if division already processed
                if (in_array($user->divisi, $processedDivisions)) {
                    continue;
                }

                $this->line("Processing division: {$user->divisi}");

                // Check if sync needed
                $divisionGroup = DivisionApprovalGroup::findByDivision($user->divisi);
                
                if ($divisionGroup && !$this->option('force')) {
                    $lastSync = $divisionGroup->last_sync;
                    if ($lastSync && $lastSync->diffInHours(now()) < 24) {
                        $this->line("  Skipping - synced recently");
                        continue;
                    }
                }

                // Sync this division
                $result = $this->syncDivision($user, $divisionGroup);
                
                if ($result['success']) {
                    if ($result['created']) {
                        $createdGroups++;
                        $this->line("  Created new approval group");
                    } else {
                        $updatedGroups++;
                        $this->line("  Updated existing approval group");
                    }
                } else {
                    $this->error("  Failed to sync: {$result['message']}");
                }

                $processedDivisions[] = $user->divisi;
            }

            $this->info("\nSynchronization completed:");
            $this->info("- Created groups: {$createdGroups}");
            $this->info("- Updated groups: {$updatedGroups}");
            $this->info("- Total divisions processed: " . count($processedDivisions));

            // Show summary of all division groups
            $this->showDivisionGroupsSummary();

        } catch (\Exception $e) {
            $this->error("Synchronization failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    protected function syncDivision(User $user, ?DivisionApprovalGroup $existingGroup): array
    {
        try {
            // This would typically call your HRIS API
            // For now, we'll create/update based on user data
            
            $divisionCode = strtolower(str_replace([' ', '-', '.'], '_', $user->divisi));
            $isNew = !$existingGroup;

            $data = [
                'division_code' => $divisionCode,
                'division_name' => $user->divisi,
                'direktorat' => $user->direktorat,
                'is_active' => true,
                'last_sync' => now()
            ];

            // Find division managers
            $this->updateDivisionManagers($data, $user->divisi);

            $divisionGroup = DivisionApprovalGroup::updateOrCreate(
                ['division_code' => $divisionCode],
                $data
            );

            return [
                'success' => true,
                'created' => $isNew,
                'group' => $divisionGroup
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function updateDivisionManagers(array &$data, string $divisionName): void
    {
        // Find managers in this division
        $manager = User::where('divisi', $divisionName)
                      ->where('level', 'Manager')
                      ->where('is_active', true)
                      ->first();

        $seniorManager = User::where('divisi', $divisionName)
                            ->where('level', 'Senior Manager')
                            ->where('is_active', true)
                            ->first();

        $generalManager = User::where('divisi', $divisionName)
                             ->where('level', 'General Manager')
                             ->where('is_active', true)
                             ->first();

        if ($manager) {
            $data['manager_nik'] = $manager->nik;
            $data['manager_name'] = $manager->name;
        }

        if ($seniorManager) {
            $data['senior_manager_nik'] = $seniorManager->nik;
            $data['senior_manager_name'] = $seniorManager->name;
        }

        if ($generalManager) {
            $data['general_manager_nik'] = $generalManager->nik;
            $data['general_manager_name'] = $generalManager->name;
        }
    }

    protected function showDivisionGroupsSummary(): void
    {
        $this->line("\n" . str_repeat('=', 80));
        $this->info('Division Approval Groups Summary');
        $this->line(str_repeat('=', 80));

        $groups = DivisionApprovalGroup::active()->orderBy('division_name')->get();

        if ($groups->isEmpty()) {
            $this->warn('No division approval groups found.');
            return;
        }

        $headers = ['Division', 'Direktorat', 'Manager', 'Senior Manager', 'General Manager'];
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                $group->division_name,
                $group->direktorat ?? '-',
                $group->manager_name ?? '-',
                $group->senior_manager_name ?? '-',
                $group->general_manager_name ?? '-'
            ];
        }

        $this->table($headers, $rows);

        // Show division groups without complete hierarchy
        $incompleteGroups = $groups->filter(function($group) {
            return !$group->hasValidApprovers();
        });

        if ($incompleteGroups->isNotEmpty()) {
            $this->line("\n");
            $this->warn("Divisions with incomplete approval hierarchy:");
            foreach ($incompleteGroups as $group) {
                $missing = [];
                if (!$group->manager_nik) $missing[] = 'Manager';
                if (!$group->senior_manager_nik) $missing[] = 'Senior Manager';
                if (!$group->general_manager_nik) $missing[] = 'General Manager';
                
                $this->line("  {$group->division_name}: Missing " . implode(', ', $missing));
            }
        }
    }
}
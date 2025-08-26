<?php
// database/seeders/DivisionApprovalGroupSeeder.php (Simple version without activity log)

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionApprovalGroupSeeder extends Seeder
{
    public function run()
    {
        // Use direct DB insert to avoid any model events and activity logging
        $divisionGroups = [
            [
                'division_code' => 'information_technology',
                'division_name' => 'Information Technology',
                'direktorat' => 'Finance, Accounting & Information Technology',
                'manager_nik' => '24060185',
                'manager_name' => 'Deddy Arianta',
                'senior_manager_nik' => '13121215',
                'senior_manager_name' => 'Rahman Gunawan',
                'general_manager_nik' => '10003001',
                'general_manager_name' => 'GM Information Technology',
                'approval_settings' => json_encode([
                    'require_manager' => true,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_code' => 'finance_accounting',
                'division_name' => 'Finance & Accounting',
                'direktorat' => 'Finance, Accounting & Information Technology',
                'manager_nik' => null,
                'manager_name' => null,
                'senior_manager_nik' => null,
                'senior_manager_name' => null,
                'general_manager_nik' => null,
                'general_manager_name' => null,
                'approval_settings' => json_encode([
                    'require_manager' => true,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_code' => 'human_resources',
                'division_name' => 'Human Resources',
                'direktorat' => 'Corporate Services',
                'manager_nik' => '20002001',
                'manager_name' => 'Manager HR',
                'senior_manager_nik' => null,
                'senior_manager_name' => null,
                'general_manager_nik' => null,
                'general_manager_name' => null,
                'approval_settings' => json_encode([
                    'require_manager' => true,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_code' => 'legal_corporate',
                'division_name' => 'Legal & Corporate',
                'direktorat' => 'Corporate Services',
                'manager_nik' => '10001002',
                'manager_name' => 'Head Legal',
                'senior_manager_nik' => null,
                'senior_manager_name' => null,
                'general_manager_nik' => null,
                'general_manager_name' => null,
                'approval_settings' => json_encode([
                    'require_manager' => false,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_code' => 'operations',
                'division_name' => 'Operations',
                'direktorat' => 'Operations & Supply Chain',
                'manager_nik' => null,
                'manager_name' => null,
                'senior_manager_nik' => null,
                'senior_manager_name' => null,
                'general_manager_nik' => '10003002',
                'general_manager_name' => 'GM Operations',
                'approval_settings' => json_encode([
                    'require_manager' => true,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_code' => 'marketing_sales',
                'division_name' => 'Marketing & Sales',
                'direktorat' => 'Commercial',
                'manager_nik' => null,
                'manager_name' => null,
                'senior_manager_nik' => '20003001',
                'senior_manager_name' => 'Senior Manager Marketing',
                'general_manager_nik' => null,
                'general_manager_name' => null,
                'approval_settings' => json_encode([
                    'require_manager' => true,
                    'require_senior_manager' => true,
                    'require_general_manager' => true,
                    'skip_if_same_person' => true,
                    'allow_self_approval' => false
                ]),
                'is_active' => true,
                'last_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Clear existing data first
        DB::table('division_approval_groups')->truncate();

        // Insert all groups
        foreach ($divisionGroups as $group) {
            DB::table('division_approval_groups')->insert($group);
        }

        $this->command->info('Division approval groups seeded successfully using direct DB insert.');
        $this->command->info('Total groups created: ' . count($divisionGroups));
        
        // Show summary
        $this->showSummary();
    }

    protected function showSummary()
    {
        $this->command->line("\n" . str_repeat('=', 80));
        $this->command->info('Division Approval Groups Summary');
        $this->command->line(str_repeat('=', 80));

        // Get data directly from database
        $groups = DB::table('division_approval_groups')
                    ->orderBy('division_name')
                    ->get();
        
        foreach ($groups as $group) {
            $this->command->line("Division: {$group->division_name}");
            $this->command->line("  Code: {$group->division_code}");
            $this->command->line("  Direktorat: {$group->direktorat}");
            $this->command->line("  Manager: " . ($group->manager_name ?? 'Not assigned'));
            $this->command->line("  Senior Manager: " . ($group->senior_manager_name ?? 'Not assigned'));
            $this->command->line("  General Manager: " . ($group->general_manager_name ?? 'Not assigned'));
            $this->command->line("");
        }

        // Show statistics
        $totalGroups = $groups->count();
        $withManager = $groups->where('manager_nik', '!=', null)->count();
        $withSeniorManager = $groups->where('senior_manager_nik', '!=', null)->count();
        $withGeneralManager = $groups->where('general_manager_nik', '!=', null)->count();

        $this->command->info("Statistics:");
        $this->command->info("- Total divisions: {$totalGroups}");
        $this->command->info("- With Manager: {$withManager}");
        $this->command->info("- With Senior Manager: {$withSeniorManager}");
        $this->command->info("- With General Manager: {$withGeneralManager}");

        // Show divisions needing attention
        $incomplete = $groups->filter(function($group) {
            return !$group->manager_nik && !$group->senior_manager_nik && !$group->general_manager_nik;
        });

        if ($incomplete->count() > 0) {
            $this->command->warn("\nDivisions needing manager assignment:");
            foreach ($incomplete as $group) {
                $missing = [];
                if (!$group->manager_nik) $missing[] = 'Manager';
                if (!$group->senior_manager_nik) $missing[] = 'Senior Manager';
                if (!$group->general_manager_nik) $missing[] = 'General Manager';
                
                $this->command->line("  {$group->division_name}: " . implode(', ', $missing));
            }
        }

        $this->command->info("\nDivision approval groups setup completed successfully!");
    }
}
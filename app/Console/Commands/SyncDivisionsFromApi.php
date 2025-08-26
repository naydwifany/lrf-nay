<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DivisionApprovalService;

class SyncDivisionsFromApi extends Command
{
    protected $signature = 'sync:divisions {--force : Force sync all divisions}';
    protected $description = 'Sync division list from API';

    protected $divisionApprovalService;

    public function __construct(DivisionApprovalService $divisionApprovalService)
    {
        parent::__construct();
        $this->divisionApprovalService = $divisionApprovalService;
    }

    public function handle()
    {
        $this->info('Starting division synchronization from API...');

        $result = $this->divisionApprovalService->syncDivisionsFromApi();

        if ($result['success']) {
            $this->info("✅ Sync completed successfully!");
            $this->info("📊 Synced divisions: {$result['synced']}");
            
            if (!empty($result['errors'])) {
                $this->warn("⚠️  Some errors occurred:");
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }
        } else {
            $this->error("❌ Sync failed: {$result['error']}");
            return 1;
        }

        // Show current divisions
        $this->showDivisionSummary();

        return 0;
    }

    protected function showDivisionSummary(): void
    {
        $this->line("\n" . str_repeat('=', 80));
        $this->info('Current Divisions Summary');
        $this->line(str_repeat('=', 80));

        $divisions = \App\Models\DivisionApprovalGroup::orderBy('direktorat')
            ->orderBy('division_name')
            ->get();

        if ($divisions->isEmpty()) {
            $this->warn('No divisions found.');
            return;
        }

        $headers = ['Code', 'Division Name', 'Directorate', 'Manager', 'Sr. Manager', 'GM'];
        $rows = [];

        foreach ($divisions as $division) {
            $rows[] = [
                $division->division_code,
                $division->division_name,
                $division->direktorat ?? '-',
                $division->manager_name ?? '-',
                $division->senior_manager_name ?? '-',
                $division->general_manager_name ?? '-'
            ];
        }

        $this->table($headers, $rows);
    }
}
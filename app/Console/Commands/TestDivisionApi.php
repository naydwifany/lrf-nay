<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DivisionApprovalService;

class TestDivisionApi extends Command
{
    protected $signature = 'test:division-api';
    protected $description = 'Test connection to division API';

    protected $divisionApprovalService;

    public function __construct(DivisionApprovalService $divisionApprovalService)
    {
        parent::__construct();
        $this->divisionApprovalService = $divisionApprovalService;
    }

    public function handle()
    {
        $this->info('Testing Division API Connection...');
        $this->line('');

        // Show configuration
        $this->info('Configuration:');
        $this->line('Admin NIK: ' . config('app.admin_nik'));
        $this->line('API URL: ' . config('app.hr_api_url'));
        $this->line('');

        // Test connection
        $result = $this->divisionApprovalService->testDivisionApiConnection();

        if ($result['success']) {
            $this->info('✅ Connection test successful!');
            $this->line('📊 ' . $result['message']);
        } else {
            $this->error('❌ Connection test failed!');
            $this->line('💥 ' . $result['message']);
            return 1;
        }

        // Ask if user wants to proceed with sync
        if ($this->confirm('Do you want to proceed with division sync?', true)) {
            $this->call('sync:divisions');
        }

        return 0;
    }
}
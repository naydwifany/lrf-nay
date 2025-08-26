<?php
// app/Console/Commands/TestWorking.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;

class TestWorking extends Command
{
    protected $signature = 'test:working';
    protected $description = 'Test working API service';

    public function handle()
    {
        $nik = '24060185';
        $password = 'eci2017';

        $this->info("Testing WorkingApiService...");

        $service = new WorkingApiService();
        
        // Test login
        $result = $service->login($nik, $password);
        
        $this->info("Login result:");
        $this->info(json_encode($result, JSON_PRETTY_PRINT));
        
        if ($result['success'] && isset($result['token'])) {
            $this->info("\n=== Testing Employee Data ===");
            
            $employeeResult = $service->getPegawaiData($nik, $result['token']);
            $this->info("Employee result:");
            $this->info(json_encode($employeeResult, JSON_PRETTY_PRINT));
            
            $this->info("🎉 SUCCESS! WorkingApiService berfungsi!");
        } else {
            $this->error("❌ WorkingApiService gagal!");
        }

        return 0;
    }
}
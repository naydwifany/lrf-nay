<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Models\User;

class TestMultipleLogins extends Command
{
    protected $signature = 'test:multiple-logins {file}';
    protected $description = 'Test multiple logins from CSV file (format: nik,password)';

    protected $workingApiService;

    public function __construct(WorkingApiService $workingApiService)
    {
        parent::__construct();
        $this->workingApiService = $workingApiService;
    }

    public function handle()
    {
        $file = $this->argument('file');
        
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $credentials = [];
        $handle = fopen($file, 'r');
        
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) >= 2) {
                $credentials[] = [
                    'nik' => trim($line[0]),
                    'password' => trim($line[1])
                ];
            }
        }
        fclose($handle);

        $this->info("Testing " . count($credentials) . " credentials...");
        $this->line('');

        $successful = 0;
        $failed = 0;

        foreach ($credentials as $cred) {
            $this->line("Testing NIK: {$cred['nik']}");
            
            $result = $this->workingApiService->login($cred['nik'], $cred['password']);
            
            if ($result['success']) {
                $this->info("  ✅ Success");
                $successful++;
                
                // Try to create user
                $this->call('test:login-create-user', [
                    'nik' => $cred['nik'],
                    'password' => $cred['password']
                ]);
            } else {
                $this->error("  ❌ Failed: " . $result['message']);
                $failed++;
            }
        }

        $this->line('');
        $this->info("Results: {$successful} successful, {$failed} failed");

        return 0;
    }
}
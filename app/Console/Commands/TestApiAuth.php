<?php
// app/Console/Commands/TestApiAuth.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EciHrisApiService;
use App\Services\ApiOnlyAuthService;

class TestApiAuth extends Command
{
    protected $signature = 'test:api-login {nik} {password}';
    protected $description = 'Test API login with real credentials';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("Testing API login with NIK: {$nik}");
        $this->info("API URL: " . config('services.eci_hris.base_url'));

        // Test 1: Direct API call
        $this->info("\n=== Testing EciHrisApiService ===");
        $apiService = new EciHrisApiService();
        
        // Test connection first
        $this->info("Testing connection...");
        $connectionTest = $apiService->testConnection();
        $this->info("Connection result: " . json_encode($connectionTest, JSON_PRETTY_PRINT));

        // Test login
        $this->info("Testing login...");
        $result = $apiService->login($nik, $password);
        $this->info("Login result: " . json_encode($result, JSON_PRETTY_PRINT));

        // Test 2: Full auth service
        $this->info("\n=== Testing ApiOnlyAuthService ===");
        try {
            $authService = new ApiOnlyAuthService($apiService);
            $authResult = $authService->authenticate($nik, $password);
            $this->info("Auth result: " . json_encode($authResult, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error("Auth service error: " . $e->getMessage());
        }

        // Test 3: Raw HTTP call
        $this->info("\n=== Testing Raw HTTP Call ===");
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->asForm()
                ->post('http://10.101.0.85/newhris_api/api/login2.php', [
                    'username' => $nik,
                    'password' => $password,
                ]);

            $this->info("Status: " . $response->status());
            $this->info("Headers: " . json_encode($response->headers(), JSON_PRETTY_PRINT));
            $this->info("Body: " . $response->body());
            
            if ($response->successful()) {
                $this->info("Response JSON: " . json_encode($response->json(), JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $this->error("Raw HTTP error: " . $e->getMessage());
        }

        return 0;
    }
}
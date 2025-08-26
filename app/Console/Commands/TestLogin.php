<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Services\ApiOnlyAuthService;

class TestLogin extends Command
{
    protected $signature = 'login:test {nik} {password}';
    protected $description = 'Test login functionality';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');
        
        $this->info("Testing login for NIK: {$nik}");
        
        // Test API service first
        $this->line("\n1. Testing WorkingApiService...");
        $workingApiService = new WorkingApiService();
        $apiResult = $workingApiService->login($nik, $password);
        
        if ($apiResult['success']) {
            $this->info("✓ API Login successful");
            $this->line("Token: " . substr($apiResult['token'], 0, 20) . "...");
        } else {
            $this->error("✗ API Login failed: " . $apiResult['message']);
        }
        
        // Test full auth service
        $this->line("\n2. Testing ApiOnlyAuthService...");
        $authService = app(ApiOnlyAuthService::class);
        $authResult = $authService->authenticate($nik, $password);
        
        if ($authResult['success']) {
            $this->info("✓ Authentication successful");
            $this->line("User: " . $authResult['user']->name);
            $this->line("Role: " . $authResult['user']->role);
        } else {
            $this->error("✗ Authentication failed: " . $authResult['message']);
        }
        
        // Test local authentication
        $this->line("\n3. Testing local authentication...");
        if (\Auth::attempt(['nik' => $nik, 'password' => $password])) {
            $this->info("✓ Local authentication successful");
            \Auth::logout();
        } else {
            $this->error("✗ Local authentication failed");
        }
    }
}

<?php
// app/Console/Commands/TestAuthCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiOnlyAuthService;
use App\Services\WorkingApiService;
use App\Models\User;

class TestAuthCommand extends Command
{
    protected $signature = 'auth:test {nik} {password}';
    protected $description = 'Test authentication service';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("🔐 Testing authentication for NIK: {$nik}");
        $this->newLine();

        // Test API Service directly
        $this->info("1️⃣ Testing WorkingApiService directly...");
        try {
            $apiService = app(WorkingApiService::class);
            $apiResult = $apiService->login($nik, $password);
            
            $this->info("   Status: " . ($apiResult['status'] ?? 'unknown'));
            $this->info("   Message: " . ($apiResult['message'] ?? 'no message'));
            
            if (isset($apiResult['data'])) {
                $this->info("   Data received: ✅");
                $this->table(['Field', 'Value'], [
                    ['NIK', $apiResult['data']['nik'] ?? 'N/A'],
                    ['Name', $apiResult['data']['name'] ?? $apiResult['data']['nama'] ?? 'N/A'],
                    ['Division', $apiResult['data']['divisi'] ?? 'N/A'],
                    ['Position', $apiResult['data']['jabatan'] ?? 'N/A'],
                    ['Level', $apiResult['data']['level'] ?? 'N/A'],
                ]);
            } else {
                $this->error("   No data received");
            }
        } catch (\Exception $e) {
            $this->error("   Exception: " . $e->getMessage());
        }

        $this->newLine();

        // Test ApiOnlyAuthService
        $this->info("2️⃣ Testing ApiOnlyAuthService...");
        try {
            $authService = app(ApiOnlyAuthService::class);
            $authResult = $authService->authenticate($nik, $password);
            
            $this->info("   Success: " . ($authResult['success'] ? '✅' : '❌'));
            $this->info("   Message: " . $authResult['message']);
            
            if ($authResult['user']) {
                $user = $authResult['user'];
                $this->info("   User created/found: ✅");
                $this->table(['Field', 'Value'], [
                    ['NIK', $user->nik],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Role', $user->role],
                    ['Division', $user->divisi],
                    ['Active', $user->is_active ? 'Yes' : 'No'],
                    ['Admin Access', $user->can_access_admin_panel ? 'Yes' : 'No'],
                ]);
            } else {
                $this->error("   No user object returned");
            }
        } catch (\Exception $e) {
            $this->error("   Exception: " . $e->getMessage());
        }

        $this->newLine();

        // Check existing user in database
        $this->info("3️⃣ Checking existing user in database...");
        $existingUser = User::where('nik', $nik)->first();
        
        if ($existingUser) {
            $this->info("   User exists in database: ✅");
            $this->table(['Field', 'Value'], [
                ['NIK', $existingUser->nik],
                ['Name', $existingUser->name],
                ['Email', $existingUser->email],
                ['Role', $existingUser->role],
                ['Active', $existingUser->is_active ? 'Yes' : 'No'],
                ['Has Password', $existingUser->password ? 'Yes' : 'No'],
                ['Login Attempts', $existingUser->login_attempts],
                ['Last Login', $existingUser->last_login_at?->format('Y-m-d H:i:s') ?? 'Never'],
            ]);

            // Test panel access
            $this->newLine();
            $this->info("4️⃣ Testing panel access...");
            
            try {
                // Test admin panel access directly using helper methods
                $canAccessAdmin = $existingUser->canAccessAdminPanel();
                $isLegal = $existingUser->isLegal();
                $isManagement = $existingUser->isManagement();
                
                $this->info("   Can access Admin Panel: " . ($canAccessAdmin ? '✅' : '❌'));
                $this->info("   Is Legal: " . ($isLegal ? '✅' : '❌'));
                $this->info("   Is Management: " . ($isManagement ? '✅' : '❌'));
                $this->info("   Is Active: " . ($existingUser->is_active ? '✅' : '❌'));
                $this->info("   Admin Panel Flag: " . ($existingUser->can_access_admin_panel ? '✅' : '❌'));
                
                // Show role-based permissions
                $this->table(['Permission', 'Status'], [
                    ['Can Access Admin Panel', $canAccessAdmin ? '✅ Yes' : '❌ No'],
                    ['Is Legal Team', $isLegal ? '✅ Yes' : '❌ No'],
                    ['Is Management', $isManagement ? '✅ Yes' : '❌ No'],
                    ['Can Approve Documents', $existingUser->canApproveDocuments() ? '✅ Yes' : '❌ No'],
                    ['Approval Level', $existingUser->getApprovalLevel()],
                ]);
                
            } catch (\Exception $e) {
                $this->error("   Panel access test failed: " . $e->getMessage());
            }
        } else {
            $this->error("   User not found in database");
        }

        $this->newLine();
        $this->info("🏁 Authentication test completed!");
    }
}
<?php
// app/Console/Commands/DebugLoginCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class DebugLoginCommand extends Command
{
    protected $signature = 'login:debug {nik} {password}';
    protected $description = 'Debug login process step by step';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("🔍 DEBUGGING LOGIN PROCESS");
        $this->info("NIK: {$nik}");
        $this->info("Password: " . str_repeat('*', strlen($password)));
        $this->newLine();

        // Step 1: Check if user exists
        $this->info("1️⃣ Checking if user exists in database...");
        $user = User::where('nik', $nik)->first();
        
        if ($user) {
            $this->info("   ✅ User found: " . $user->name);
            $this->table(['Property', 'Value'], [
                ['NIK', $user->nik],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
                ['Is Active', $user->is_active ? 'Yes' : 'No'],
                ['Has Password', $user->password ? 'Yes' : 'No'],
                ['Login Attempts', $user->login_attempts],
                ['Admin Panel Access', $user->can_access_admin_panel ? 'Yes' : 'No'],
            ]);
        } else {
            $this->error("   ❌ User not found");
        }
        $this->newLine();

        // Step 2: Test password verification (if user exists locally)
        if ($user && $user->password) {
            $this->info("2️⃣ Testing local password verification...");
            $passwordMatch = Hash::check($password, $user->password);
            $this->info("   Password match: " . ($passwordMatch ? '✅ Yes' : '❌ No'));
            $this->newLine();
        }

        // Step 3: Test API Service
        $this->info("3️⃣ Testing WorkingApiService...");
        try {
            $apiService = app(WorkingApiService::class);
            
            // Test login method
            $this->info("   Testing login() method...");
            $loginResult = $apiService->login($nik, $password);
            $this->info("   Login result: " . ($loginResult['success'] ? '✅ Success' : '❌ Failed'));
            $this->info("   Message: " . ($loginResult['message'] ?? 'No message'));
            
            if (isset($loginResult['data'])) {
                $this->info("   Data keys: " . implode(', ', array_keys($loginResult['data'])));
            }
            
            // Test authenticate method
            $this->info("   Testing authenticate() method...");
            $authResult = $apiService->authenticate($nik, $password);
            $this->info("   Auth result: " . ($authResult['success'] ? '✅ Success' : '❌ Failed'));
            $this->info("   Message: " . ($authResult['message'] ?? 'No message'));
            
            if ($authResult['user']) {
                $this->info("   User object: ✅ Returned");
                $this->info("   User name: " . $authResult['user']->name);
                $this->info("   User active: " . ($authResult['user']->is_active ? 'Yes' : 'No'));
            } else {
                $this->error("   User object: ❌ Not returned");
            }
            
        } catch (\Exception $e) {
            $this->error("   💥 Exception: " . $e->getMessage());
        }
        $this->newLine();

        // Step 4: Test Panel Access
        if ($user) {
            $this->info("4️⃣ Testing panel access...");
            
            try {
                $canAccessAdmin = $user->canAccessAdminPanel();
                $this->info("   Can access admin panel: " . ($canAccessAdmin ? '✅ Yes' : '❌ No'));
                
                $this->table(['Permission Check', 'Result'], [
                    ['Is Active', $user->is_active ? '✅ Yes' : '❌ No'],
                    ['Is Legal', $user->isLegal() ? '✅ Yes' : '❌ No'],
                    ['Is Management', $user->isManagement() ? '✅ Yes' : '❌ No'],
                    ['Admin Flag', $user->can_access_admin_panel ? '✅ Yes' : '❌ No'],
                ]);
                
            } catch (\Exception $e) {
                $this->error("   💥 Panel access error: " . $e->getMessage());
            }
        }
        $this->newLine();

        // Step 5: Test Laravel Auth
        $this->info("5️⃣ Testing Laravel Auth::attempt...");
        try {
            if ($user) {
                $authAttempt = Auth::attempt(['nik' => $nik, 'password' => $password]);
                $this->info("   Auth::attempt result: " . ($authAttempt ? '✅ Success' : '❌ Failed'));
                
                if ($authAttempt) {
                    $this->info("   Authenticated user: " . Auth::user()->name);
                    Auth::logout(); // Logout untuk cleanup
                }
            } else {
                $this->warn("   ⚠️ Cannot test Auth::attempt - user not found");
            }
        } catch (\Exception $e) {
            $this->error("   💥 Auth::attempt error: " . $e->getMessage());
        }
        $this->newLine();

        // Step 6: Recommendations
        $this->info("6️⃣ RECOMMENDATIONS:");
        
        if (!$user) {
            $this->warn("   ⚠️ User not found - create user first");
            $this->info("   💡 Run: php artisan user:fix {$nik} {$password}");
        } elseif (!$user->is_active) {
            $this->warn("   ⚠️ User is not active - activate user");
            $this->info("   💡 Run: User::where('nik', '{$nik}')->update(['is_active' => true]);");
        } elseif ($user->login_attempts >= 5) {
            $this->warn("   ⚠️ User has too many login attempts - reset attempts");
            $this->info("   💡 Run: User::where('nik', '{$nik}')->update(['login_attempts' => 0]);");
        } elseif (!$user->password) {
            $this->warn("   ⚠️ User has no password - set password");
            $this->info("   💡 Run: User::where('nik', '{$nik}')->update(['password' => bcrypt('{$password}')]);");
        } elseif (!$user->canAccessAdminPanel()) {
            $this->warn("   ⚠️ User cannot access admin panel - check role and permissions");
            $this->info("   💡 Legal roles need admin access flag or proper role");
        } else {
            $this->info("   ✅ User looks good - check application logs for login errors");
        }

        $this->newLine();
        $this->info("🏁 Debug completed!");
    }
}
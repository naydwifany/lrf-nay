<?php
// app/Console/Commands/QuickUserFix.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class QuickUserFix extends Command
{
    protected $signature = 'user:quick-fix {nik} {password}';
    protected $description = 'Quick fix for user login issues';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("🚀 Quick fixing user: {$nik}");

        $user = User::where('nik', $nik)->first();
        
        if (!$user) {
            $this->error("❌ User not found, creating new user...");
            
            $user = User::create([
                'nik' => $nik,
                'name' => 'Test User ' . $nik,
                'email' => $nik . '@company.com',
                'password' => Hash::make($password),
                'role' => 'admin_legal',
                'jabatan' => 'Admin Legal',
                'divisi' => 'Legal',
                'is_active' => true,
                'can_access_admin_panel' => true,
                'login_attempts' => 0,
            ]);
            
            $this->info("✅ User created");
        } else {
            $this->info("👤 User found, fixing issues...");
        }

        // Fix all potential issues
        $user->update([
            'password' => Hash::make($password),  // Set password
            'is_active' => true,                  // Activate
            'login_attempts' => 0,                // Reset attempts
            'can_access_admin_panel' => true,    // Grant admin access
        ]);

        // Ensure legal role
        if (!in_array($user->role, ['admin_legal', 'head_legal', 'reviewer_legal', 'legal'])) {
            $user->update(['role' => 'admin_legal']);
        }

        $this->newLine();
        $this->info("✅ User fixed successfully!");
        
        $this->table(['Property', 'Value'], [
            ['NIK', $user->nik],
            ['Name', $user->name],
            ['Role', $user->role],
            ['Is Active', $user->is_active ? 'Yes' : 'No'],
            ['Admin Access', $user->can_access_admin_panel ? 'Yes' : 'No'],
            ['Login Attempts', $user->login_attempts],
            ['Can Access Admin Panel', $user->canAccessAdminPanel() ? 'Yes' : 'No'],
            ['Is Legal', $user->isLegal() ? 'Yes' : 'No'],
        ]);

        $this->newLine();
        $this->info("🎯 Try logging in now!");
        $this->info("🔗 Admin Panel: http://127.0.0.1:8000/admin");
        $this->info("🔗 User Panel: http://127.0.0.1:8000/user");
    }
}
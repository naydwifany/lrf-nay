<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\ApiOnlyAuthService;

class TestExistingUser extends Command
{
    protected $signature = 'test:existing-user {nik}';
    protected $description = 'Test existing user with current model structure';

    public function handle()
    {
        $nik = $this->argument('nik');
        $user = User::where('nik', $nik)->first();

        if (!$user) {
            $this->error("User with NIK {$nik} not found");
            return 1;
        }

        $this->info("Testing user: {$user->name} ({$user->nik})");
        $this->line('');

        // Test panel access
        $this->testPanelAccess($user);

        // Test role methods
        $this->testRoleMethods($user);

        // Test authentication if possible
        $this->testAuthentication($user);

        return 0;
    }

    protected function testPanelAccess($user): void
    {
        $this->info('🔐 Panel Access Test:');
        
        // Mock panel objects
        $adminPanel = new class { public function getId() { return 'admin'; } };
        $userPanel = new class { public function getId() { return 'user'; } };

        $canAccessAdmin = $user->canAccessPanel($adminPanel);
        $canAccessUser = $user->canAccessPanel($userPanel);

        $this->line("Admin Panel: " . ($canAccessAdmin ? '✅ Yes' : '❌ No'));
        $this->line("User Panel: " . ($canAccessUser ? '✅ Yes' : '❌ No'));
        
        if ($canAccessAdmin) {
            $reasons = [];
            if ($user->isLegal()) $reasons[] = 'Legal Team';
            if ($user->isManagement()) $reasons[] = 'Management';
            $this->line("  Reason: " . implode(', ', $reasons));
        }
        $this->line('');
    }

    protected function testRoleMethods($user): void
    {
        $this->info('👤 Role Methods Test:');
        
        $roles = [
            'isLegal' => $user->isLegal(),
            'isManager' => $user->isManager(),
            'isSeniorManager' => $user->isSeniorManager(),
            'isGeneralManager' => $user->isGeneralManager(),
            'isDirector' => $user->isDirector(),
            'isSupervisor' => $user->isSupervisor(),
            'isManagement' => $user->isManagement(),
            'canAccessAdminPanel' => $user->canAccessAdminPanel(),
        ];

        foreach ($roles as $method => $result) {
            $this->line("{$method}: " . ($result ? '✅ Yes' : '❌ No'));
        }
        $this->line('');
    }

    protected function testAuthentication($user): void
    {
        $this->info('🔑 Authentication Test:');
        
        if (!$user->api_token) {
            $this->warn('No API token found - user may need to login via API first');
            return;
        }

        $this->line('API Token: Available');
        $this->line('Is Local User: ' . ($user->isLocalUser() ? 'Yes' : 'No'));
        $this->line('Last API Sync: ' . ($user->last_api_sync ? $user->last_api_sync->diffForHumans() : 'Never'));
        $this->line('Last Login: ' . ($user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never'));
    }
}
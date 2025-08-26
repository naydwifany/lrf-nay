<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ListUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'list:users {--role= : Filter by specific role}';

    /**
     * The console command description.
     */
    protected $description = 'List all users with their roles for discussion testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('👥 USER LIST FOR DISCUSSION TESTING');
        $this->line('====================================');

        $roleFilter = $this->option('role');
        
        // Get users
        $query = User::query();
        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }
        
        $users = $query->orderBy('role')->orderBy('name')->get();
        
        if ($users->isEmpty()) {
            $this->warn('No users found!');
            return;
        }
        
        // Group by role
        $usersByRole = $users->groupBy('role');
        
        foreach ($usersByRole as $role => $roleUsers) {
            $this->newLine();
            $this->info("🔹 ROLE: " . strtoupper($role) . " ({$roleUsers->count()} users)");
            
            $tableData = $roleUsers->map(function($user) {
                return [
                    $user->nik,
                    $user->name,
                    $user->email ?? 'No Email',
                    $user->divisi ?? 'No Division'
                ];
            })->toArray();
            
            $this->table(['NIK', 'Name', 'Email', 'Division'], $tableData);
        }
        
        $this->newLine();
        $this->info("📊 SUMMARY:");
        $this->info("Total users: " . $users->count());
        $this->info("Total roles: " . $usersByRole->count());
        
        // Show discussion-relevant roles
        $this->newLine();
        $this->info("🎯 DISCUSSION-RELEVANT ROLES:");
        $discussionRoles = [
            'head_legal' => 'Can close discussions, full access',
            'finance' => 'Required for discussion closure',
            'general_manager' => 'Full access to discussions',
            'reviewer_legal' => 'Full access to discussions',
            'admin_legal' => 'Can participate in discussions'
        ];
        
        foreach ($discussionRoles as $role => $description) {
            $count = $usersByRole->get($role, collect())->count();
            $status = $count > 0 ? "✅ {$count} users" : "❌ No users";
            $this->line("  {$role}: {$description} - {$status}");
        }
        
        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('  - Use --role=<role> to filter by specific role');
        $this->line('  - Use "php artisan check:discussion --user-nik=<nik>" to test specific user access');
    }
}
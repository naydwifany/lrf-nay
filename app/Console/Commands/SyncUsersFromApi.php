<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CustomAuthService;
use App\Models\User;

class SyncUsersFromApi extends Command
{
    protected $signature = 'users:sync-from-api 
                            {--nik=* : Specific NIKs to sync}
                            {--all : Sync all users}
                            {--dry-run : Show what would be updated without making changes}';
    
    protected $description = 'Sync users from external API';

    public function handle()
    {
        if ($this->option('all')) {
            $this->syncAllUsers();
        } elseif ($this->option('nik')) {
            $this->syncSpecificUsers($this->option('nik'));
        } else {
            $this->syncRecentUsers();
        }
    }

    private function syncAllUsers()
    {
        $this->info('Syncing all users from API...');
        
        // You would implement API endpoint to get all users
        // For now, we'll sync users who have been active recently
        $users = User::where('last_api_sync', '<', now()->subDays(7))
                    ->orWhereNull('last_api_sync')
                    ->get();

        $this->syncUsers($users);
    }

    private function syncSpecificUsers(array $niks)
    {
        $this->info('Syncing specific users: ' . implode(', ', $niks));
        
        $users = User::whereIn('nik', $niks)->get();
        $this->syncUsers($users);
    }

    private function syncRecentUsers()
    {
        $this->info('Syncing recently active users...');
        
        $users = User::where('last_api_sync', '<', now()->subHours(24))
                    ->where('is_active', true)
                    ->get();

        $this->syncUsers($users);
    }

    private function syncUsers($users)
    {
        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        $synced = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Here you would call an API endpoint to get updated user data
                // For demonstration, we'll just update the last_sync timestamp
                
                if (!$this->option('dry-run')) {
                    $user->update(['last_api_sync' => now()]);
                }
                
                $synced++;
                
            } catch (\Exception $e) {
                $this->error("Error syncing user {$user->nik}: " . $e->getMessage());
                $errors++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Sync completed!");
        $this->line("✅ Synced: {$synced}");
        
        if ($errors > 0) {
            $this->line("❌ Errors: {$errors}");
        }
    }
}
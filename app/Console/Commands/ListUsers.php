<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ListUsers extends Command
{
    protected $signature = 'list:users {--role=} {--active} {--with-api-token}';
    protected $description = 'List all users with optional filters';

    public function handle()
    {
        $query = User::query();

        // Apply filters
        if ($this->option('role')) {
            $query->where('role', $this->option('role'));
        }

        if ($this->option('active')) {
            $query->where('is_active', true);
        }

        if ($this->option('with-api-token')) {
            $query->whereNotNull('api_token');
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        if ($users->isEmpty()) {
            $this->warn('No users found with the specified criteria');
            return 0;
        }

        $this->info('Found ' . $users->count() . ' users:');
        $this->line('');

        $headers = ['NIK', 'Name', 'Role', 'Level', 'Divisi', 'Active', 'Last Login', 'API Token'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->nik,
                $user->name,
                $user->role,
                $user->level_tier ?? $user->level,
                $user->divisi ?? '-',
                $user->is_active ? 'Yes' : 'No',
                $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                $user->api_token ? 'Yes' : 'No'
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
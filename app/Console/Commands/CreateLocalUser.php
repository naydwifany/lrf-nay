<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HybridAuthService;
use App\Models\User;

class CreateLocalUser extends Command
{
    protected $signature = 'user:create-local 
                            {nik : User NIK}
                            {name : User full name}
                            {email : User email}
                            {password : User password}
                            {role : User role}
                            {--division= : User division}
                            {--jabatan= : User position}';

    protected $description = 'Create a local user that can login without API';

    public function handle()
    {
        $nik = $this->argument('nik');
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');
        $role = $this->argument('role');
        $division = $this->option('division') ?? 'Local Division';
        $jabatan = $this->option('jabatan') ?? 'Local User';

        // Check if user already exists
        if (User::where('nik', $nik)->exists()) {
            $this->error("User with NIK {$nik} already exists!");
            return 1;
        }

        try {
            $hybridAuthService = app(HybridAuthService::class);
            
            $user = $hybridAuthService->createLocalUser([
                'nik' => $nik,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'divisi' => $division,
                'jabatan' => $jabatan,
                'level' => 'Local User',
                'department' => 'Local',
                'direktorat' => 'Local'
            ]);

            $this->info("✅ Local user created successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['NIK', $user->nik],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Role', $user->role],
                    ['Division', $user->divisi],
                    ['Is Local User', $user->is_local_user ? 'Yes' : 'No'],
                    ['Created At', $user->created_at]
                ]
            );

            $this->warn("⚠️  This user can login with NIK: {$nik} and the password you provided.");
            $this->info("💡 Local users bypass API authentication and are stored in local database.");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create local user: " . $e->getMessage());
            return 1;
        }
    }
}
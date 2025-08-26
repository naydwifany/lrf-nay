<?php
// database/seeders/UserSeeder.php (Fixed)

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;  // Add this missing import
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating minimal user set - only legal team for admin panel access...');
        
        // Only create legal team users to access admin panel
        // All other users will be created automatically when they login via API
        $legalUsers = [
            [
                'nik' => '99999001',
                'name' => 'Super Admin Legal',
                'email' => 'admin@electroniccity.co.id',
                'password' => Hash::make('admin123'),
                'divisi' => 'Legal & Corporate',
                'department' => 'Legal',
                'jabatan' => 'Super Admin Legal',
                'level' => 'Administrator',
                'direktorat' => 'Corporate Services',
                'role' => 'admin_legal',
                'is_active' => true,
                'email_verified_at' => now(),
                'last_api_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nik' => '99999002',
                'name' => 'Head Legal System',
                'email' => 'head.legal@electroniccity.co.id',
                'password' => Hash::make('headlegal123'),
                'divisi' => 'Legal & Corporate',
                'department' => 'Legal',
                'jabatan' => 'Head Legal',
                'level' => 'Manager',
                'direktorat' => 'Corporate Services',
                'role' => 'head_legal',
                'is_active' => true,
                'email_verified_at' => now(),
                'last_api_sync' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Clear existing users (optional)
        if ($this->command->confirm('Clear existing users?', false)) {
            DB::table('users')->truncate();
            $this->command->info('Existing users cleared.');
        }

        // Insert legal team users using DB facade
        foreach ($legalUsers as $userData) {
            try {
                DB::table('users')->updateOrInsert(
                    ['nik' => $userData['nik']],
                    $userData
                );
                $this->command->line("✓ Created user: {$userData['name']} (NIK: {$userData['nik']})");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to create user {$userData['name']}: " . $e->getMessage());
            }
        }

        $this->command->info('Minimal legal team users created successfully.');
        
        // Verify users were created
        $userCount = DB::table('users')->whereIn('nik', ['99999001', '99999002'])->count();
        $this->command->info("Total legal users in database: {$userCount}");
        
        // Show credentials
        $this->showCredentials();
        
        // Show instructions
        $this->showInstructions();
    }

    protected function showCredentials()
    {
        $this->command->line("\n" . str_repeat('=', 80));
        $this->command->info('Admin Panel Login Credentials');
        $this->command->line(str_repeat('=', 80));

        $credentials = [
            ['NIK' => '99999001', 'Name' => 'Super Admin Legal', 'Password' => 'admin123', 'Access' => '/admin'],
            ['NIK' => '99999002', 'Name' => 'Head Legal System', 'Password' => 'headlegal123', 'Access' => '/admin']
        ];

        $this->command->table(['NIK', 'Name', 'Password', 'Access Panel'], $credentials);
        
        $this->command->line("\nThese users can access the admin panel for legal document management.");
        $this->command->warn("Change these passwords in production!");
    }

    protected function showInstructions()
    {
        $this->command->line("\n" . str_repeat('=', 80));
        $this->command->info('How the System Works');
        $this->command->line(str_repeat('=', 80));

        $this->command->line("1. Legal Team Login:");
        $this->command->line("   - Use the NIK and passwords above");
        $this->command->line("   - Access admin panel at: /admin");
        $this->command->line("   - Manage all legal documents and approvals");
        
        $this->command->line("\n2. Regular Users (Managers, Directors, etc.):");
        $this->command->line("   - Use their actual company NIK and password");
        $this->command->line("   - System will automatically get data from HRIS API");
        $this->command->line("   - User account created automatically on first login");
        $this->command->line("   - Access user panel at: /user");
        
        $this->command->line("\n3. API Integration:");
        $this->command->line("   - All user data comes from HRIS API");
        $this->command->line("   - Roles assigned automatically based on jabatan/level");
        $this->command->line("   - Division approval groups created automatically");
        
        $this->command->line("\n4. No Manual User Creation Needed:");
        $this->command->line("   - Just configure HRIS API credentials in .env");
        $this->command->line("   - Users login with their company credentials");
        $this->command->line("   - System handles everything automatically");

        $this->command->line("\n" . str_repeat('-', 80));
        $this->command->info('Next Steps:');
        $this->command->line("1. Configure HRIS API in .env file");
        $this->command->line("2. Test legal team login at /admin");
        $this->command->line("3. Ask a real employee to test login at /user");
        $this->command->line("4. Check that their data is synced correctly");
        
        $this->command->line("\n" . str_repeat('-', 80));
        $this->command->info('Testing Commands:');
        $this->command->line("# Verify users created:");
        $this->command->line("php artisan tinker --execute=\"DB::table('users')->select('nik', 'name', 'role')->get()\"");
        
        $this->command->line("\n# Test login page:");
        $this->command->line("Visit: http://localhost:8000/login");
        
        $this->command->line("\n# Test admin panel:");
        $this->command->line("Visit: http://localhost:8000/admin");
        
        $this->command->line(str_repeat('=', 80));
    }
}
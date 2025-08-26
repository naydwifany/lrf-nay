<?php
// app/Console/Commands/TestLoginCreateUser.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Services\ApiOnlyAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestLoginCreateUser extends Command
{
    protected $signature = 'test:login-create-user {nik} {password}';
    protected $description = 'Test login via API and create user if successful';

    protected $workingApiService;
    protected $apiAuthService;

    public function __construct(WorkingApiService $workingApiService, ApiOnlyAuthService $apiAuthService)
    {
        parent::__construct();
        $this->workingApiService = $workingApiService;
        $this->apiAuthService = $apiAuthService;
    }

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("🔐 Testing Login for NIK: {$nik}");
        $this->line(str_repeat('=', 60));

        // Step 1: Test API Login
        $this->info('Step 1: Testing API Login...');
        $loginResult = $this->workingApiService->login($nik, $password);

        if (!$loginResult['success']) {
            $this->error('❌ Login failed: ' . $loginResult['message']);
            return 1;
        }

        $this->info('✅ Login successful!');
        $token = $loginResult['token'];
        $basicData = $loginResult['data'];

        $this->line('Token: ' . substr($token, 0, 20) . '...');
        $this->showDataStructure('Basic Login Data', $basicData);

        // Step 2: Get Employee Data
        $this->info('Step 2: Getting Employee Data...');
        $employeeResult = $this->workingApiService->getPegawaiData($nik, $token);

        if (!$employeeResult['success']) {
            $this->warn('⚠️  Failed to get employee data: ' . $employeeResult['message']);
            $this->line('Will use basic login data only');
            $finalData = $basicData;
        } else {
            $this->info('✅ Employee data retrieved!');
            $employeeData = $employeeResult['data'];
            $this->showDataStructure('Employee Data', $employeeData);
            
            // Merge data
            $finalData = $employeeData;
            if (isset($basicData['pegawai'])) {
                $finalData['pegawai'] = array_merge(
                    $finalData['pegawai'] ?? [],
                    $basicData['pegawai']
                );
            }
        }

        // Step 3: Create/Update User
        $this->info('Step 3: Creating/Updating User...');
        $user = $this->createUserFromApiData($finalData, $token, $password);

        if ($user) {
            $this->info('✅ User created/updated successfully!');
            $this->showUserInfo($user);
        } else {
            $this->error('❌ Failed to create user');
            return 1;
        }

        // Step 4: Test Authentication Service
        $this->info('Step 4: Testing Authentication Service...');
        $authResult = $this->apiAuthService->authenticate($nik, $password);

        if ($authResult['success']) {
            $this->info('✅ Authentication service working!');
            $this->line('Auth method: ' . $authResult['auth_method']);
        } else {
            $this->error('❌ Authentication service failed: ' . $authResult['message']);
        }

        $this->line('');
        $this->info('🎉 All tests completed!');
        $this->line('You can now login with NIK: ' . $nik);

        return 0;
    }

    protected function showDataStructure(string $title, array $data): void
    {
        $this->line('');
        $this->line("📋 {$title}:");
        $this->line(str_repeat('-', 40));

        $this->showArrayRecursive($data, 0);
        $this->line('');
    }

    protected function showArrayRecursive(array $data, int $level): void
    {
        $indent = str_repeat('  ', $level);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line("{$indent}{$key}: [array with " . count($value) . " items]");
                if ($level < 2) { // Limit depth
                    $this->showArrayRecursive($value, $level + 1);
                }
            } else {
                $displayValue = is_string($value) ? 
                    (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : 
                    $value;
                $this->line("{$indent}{$key}: {$displayValue}");
            }
        }
    }

    protected function createUserFromApiData(array $apiData, string $token, string $password): ?User
    {
        try {
            $pegawai = $apiData['pegawai'] ?? [];
            $atasan = $apiData['atasan'] ?? null;

            if (empty($pegawai) || !isset($pegawai['nik'])) {
                $this->error('❌ No pegawai data found in API response');
                return null;
            }

            // Determine role
            $role = $this->determineUserRole($pegawai);

            // Generate email
            $email = $this->generateEmail($pegawai['nik'], $pegawai['nama'] ?? 'User');

            // Prepare user data
            $userData = [
                'name' => $pegawai['nama'] ?? 'Unknown',
                'email' => $email,
                'password' => Hash::make($password),
                'pegawai_id' => $pegawai['pegawaiid'] ?? null,
                'department' => $pegawai['departemen'] ?? null,
                'divisi' => $pegawai['divisi'] ?? null,
                'divisi_code' => $pegawai['divisicode'] ?? null,
                'seksi' => $pegawai['seksi'] ?? null,
                'subseksi' => $pegawai['subseksi'] ?? null,
                'jabatan' => $pegawai['jabatan'] ?? null,
                'level' => $pegawai['level'] ?? null,
                'level_tier' => $this->workingApiService->mapLevelToTier($pegawai['level'] ?? '1'),
                'supervisor_nik' => $atasan['nik'] ?? null,
                'satker_id' => $pegawai['satkerid'] ?? null,
                'direktorat' => $pegawai['direktorat'] ?? null,
                'unit_name' => $pegawai['unitname'] ?? null,
                'api_token' => $token,
                'last_api_sync' => now(),
                'is_active' => true,
                'role' => $role,
                'api_data' => $apiData,
                'email_verified_at' => now(),
                'last_login_at' => now()
            ];

            // Create or update user
            $user = User::updateOrCreate(
                ['nik' => $pegawai['nik']],
                $userData
            );

            $this->line('✅ User data saved to database');
            return $user;

        } catch (\Exception $e) {
            $this->error('❌ Error creating user: ' . $e->getMessage());
            return null;
        }
    }

    protected function determineUserRole(array $pegawai): string
    {
        $level = strtolower($pegawai['level'] ?? '');
        $jabatan = strtolower($pegawai['jabatan'] ?? '');

        // Legal roles
        if (str_contains($jabatan, 'legal')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_legal';
            } elseif (str_contains($jabatan, 'admin')) {
                return 'admin_legal';
            } elseif (str_contains($jabatan, 'reviewer') || str_contains($jabatan, 'review')) {
                return 'reviewer_legal';
            } else {
                return 'legal';
            }
        }

        // Finance roles
        if (str_contains($jabatan, 'finance') || str_contains($jabatan, 'keuangan')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_finance';
            } else {
                return 'finance';
            }
        }

        // Management levels
        if ($level === '6' || str_contains($jabatan, 'general manager')) {
            return 'general_manager';
        } elseif ($level === '5' || str_contains($jabatan, 'senior manager')) {
            return 'senior_manager';
        } elseif ($level === '4' || str_contains($jabatan, 'manager')) {
            return 'manager';
        } elseif ($level === '3' || str_contains($jabatan, 'supervisor')) {
            return 'supervisor';
        } elseif ($level === '7' || str_contains($jabatan, 'director') || str_contains($jabatan, 'direktur')) {
            return 'director';
        }

        return 'user';
    }

    protected function generateEmail(string $nik, string $name): string
    {
        $domain = 'electroniccity.co.id';
        $cleanName = strtolower(str_replace(' ', '.', $name));
        $cleanName = preg_replace('/[^a-z0-9.]/', '', $cleanName);
        return "{$cleanName}.{$nik}@{$domain}";
    }

    protected function showUserInfo(User $user): void
    {
        $this->line('');
        $this->info('👤 User Information:');
        $this->table(
            ['Field', 'Value'],
            [
                ['NIK', $user->nik],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
                ['Level', $user->level],
                ['Level Tier', $user->level_tier],
                ['Jabatan', $user->jabatan],
                ['Divisi', $user->divisi],
                ['Department', $user->department],
                ['Direktorat', $user->direktorat],
                ['Is Active', $user->is_active ? 'Yes' : 'No'],
                ['Last Login', $user->last_login_at],
                ['Created', $user->created_at],
                ['Updated', $user->updated_at],
            ]
        );
    }
}


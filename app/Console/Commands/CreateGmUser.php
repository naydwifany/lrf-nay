<?php
// app/Console/Commands/CreateGmUser.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateGmUser extends Command
{
    protected $signature = 'create:gm-user {--nik=} {--password=} {--force}';
    protected $description = 'Create GM user from API or manual data';

    protected $workingApiService;

    public function __construct(WorkingApiService $workingApiService)
    {
        parent::__construct();
        $this->workingApiService = $workingApiService;
    }

    public function handle()
    {
        $this->info('🔨 Create GM User');
        $this->line(str_repeat('=', 50));

        // Get credentials
        $nik = $this->option('nik') ?: $this->ask('Enter GM NIK');
        $password = $this->option('password') ?: $this->secret('Enter GM Password');

        if (!$nik || !$password) {
            $this->error('NIK and password are required');
            return 1;
        }

        // Check if user already exists
        $existingUser = User::where('nik', $nik)->first();
        if ($existingUser && !$this->option('force')) {
            $this->warn("User with NIK {$nik} already exists!");
            $this->showUserInfo($existingUser);
            
            if (!$this->confirm('Update existing user?', true)) {
                return 0;
            }
        }

        $this->info("Creating/updating user for NIK: {$nik}");

        // Try API first
        $this->line('🔄 Trying to get data from API...');
        $apiResult = $this->tryApiLogin($nik, $password);

        if ($apiResult['success']) {
            $user = $apiResult['user'];
            $this->info('✅ User created from API data');
        } else {
            $this->warn('⚠️  API failed: ' . $apiResult['message']);
            
            if ($this->confirm('Create user manually?', true)) {
                $user = $this->createManualUser($nik, $password);
                $this->info('✅ User created manually');
            } else {
                $this->error('User creation cancelled');
                return 1;
            }
        }

        $this->showUserInfo($user);

        // Test login
        if ($this->confirm('Test login with this user?', true)) {
            $this->testUserLogin($user, $password);
        }

        return 0;
    }

    protected function tryApiLogin(string $nik, string $password): array
    {
        try {
            // Step 1: Login
            $loginResult = $this->workingApiService->login($nik, $password);
            
            if (!$loginResult['success']) {
                return [
                    'success' => false,
                    'message' => $loginResult['message'],
                    'user' => null
                ];
            }

            $token = $loginResult['token'];
            $basicData = $loginResult['data'];

            // Step 2: Get employee data
            $employeeResult = $this->workingApiService->getPegawaiData($nik, $token);
            
            $finalData = $employeeResult['success'] ? $employeeResult['data'] : $basicData;

            // Step 3: Create user
            $user = $this->createUserFromApiData($finalData, $token, $password);

            return [
                'success' => true,
                'message' => 'User created from API',
                'user' => $user
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API Error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    protected function createUserFromApiData(array $apiData, string $token, string $password): User
    {
        $pegawai = $apiData['pegawai'] ?? [];
        $atasan = $apiData['atasan'] ?? null;

        // Show API data
        $this->line('📋 API Data received:');
        foreach ($pegawai as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $this->line("  {$key}: {$value}");
            }
        }

        $userData = [
            'name' => $pegawai['nama'] ?? 'GM User',
            'email' => $this->generateEmail($pegawai['nik'], $pegawai['nama'] ?? 'GM User'),
            'password' => Hash::make($password),
            'pegawai_id' => $pegawai['pegawaiid'] ?? null,
            'department' => $pegawai['departemen'] ?? null,
            'divisi' => $pegawai['divisi'] ?? 'Management',
            'divisi_code' => $pegawai['divisicode'] ?? 'MGT',
            'seksi' => $pegawai['seksi'] ?? null,
            'subseksi' => $pegawai['subseksi'] ?? null,
            'jabatan' => $pegawai['jabatan'] ?? 'General Manager',
            'level' => $pegawai['level'] ?? '6',
            'level_tier' => $this->workingApiService->mapLevelToTier($pegawai['level'] ?? '6'),
            'supervisor_nik' => $atasan['nik'] ?? null,
            'satker_id' => $pegawai['satkerid'] ?? null,
            'direktorat' => $pegawai['direktorat'] ?? 'Management',
            'unit_name' => $pegawai['unitname'] ?? null,
            'api_token' => $token,
            'last_api_sync' => now(),
            'is_active' => true,
            'role' => 'general_manager',
            'api_data' => $apiData,
            'email_verified_at' => now(),
            'last_login_at' => now()
        ];

        return User::updateOrCreate(['nik' => $pegawai['nik']], $userData);
    }

    protected function createManualUser(string $nik, string $password): User
    {
        $name = $this->ask('Enter user name', 'GM User');
        $divisi = $this->ask('Enter division', 'Management');
        $jabatan = $this->ask('Enter position', 'General Manager');
        $direktorat = $this->ask('Enter directorate', 'Management');

        $userData = [
            'name' => $name,
            'email' => $this->generateEmail($nik, $name),
            'password' => Hash::make($password),
            'divisi' => $divisi,
            'divisi_code' => 'MGT',
            'jabatan' => $jabatan,
            'level' => '6',
            'level_tier' => 'General Manager',
            'direktorat' => $direktorat,
            'is_active' => true,
            'role' => 'general_manager',
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        return User::updateOrCreate(['nik' => $nik], $userData);
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
                ['API Token', $user->api_token ? 'Available' : 'None'],
            ]
        );
    }

    protected function testUserLogin(User $user, string $password): void
    {
        $this->line('');
        $this->info('🔐 Testing user login...');

        // Test API login
        $apiResult = $this->workingApiService->login($user->nik, $password);
        
        if ($apiResult['success']) {
            $this->info('✅ API login successful');
        } else {
            $this->error('❌ API login failed: ' . $apiResult['message']);
        }

        // Test hash verification
        if (Hash::check($password, $user->password)) {
            $this->info('✅ Password hash verification successful');
        } else {
            $this->error('❌ Password hash verification failed');
        }

        // Test authentication service
        try {
            $authService = app(\App\Services\ApiOnlyAuthService::class);
            $authResult = $authService->authenticate($user->nik, $password);
            
            if ($authResult['success']) {
                $this->info('✅ Authentication service successful');
            } else {
                $this->error('❌ Authentication service failed: ' . $authResult['message']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Authentication service error: ' . $e->getMessage());
        }
    }
}
<?php 

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use App\Models\User;

class TestApiLogin extends Command
{
    protected $signature = 'test:api-login {nik} {password}';
    protected $description = 'Test API login functionality';

    public function handle()
    {
        $nik = $this->argument('nik');
        $password = $this->argument('password');

        $this->info("Testing API login for NIK: {$nik}");

        $apiService = app(WorkingApiService::class);
        $result = $apiService->login($nik, $password);

        if ($result['success']) {
            $this->info("✅ API Login successful!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Token', $result['token'] ?? 'N/A'],
                    ['User Data', json_encode($result['data'], JSON_PRETTY_PRINT)]
                ]
            );

            // Test creating/updating user
            if (isset($result['data']['pegawai'])) {
                $authService = app(\App\Services\ApiOnlyAuthService::class);
                $authResult = $authService->authenticate($nik, $password);
                
                if ($authResult['success']) {
                    $user = $authResult['user'];
                    $this->info("✅ User created/updated successfully!");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['NIK', $user->nik],
                            ['Name', $user->name],
                            ['Role', $user->role],
                            ['Division', $user->divisi],
                            ['Level', $user->level]
                        ]
                    );
                } else {
                    $this->error("❌ Failed to create/update user: " . $authResult['message']);
                }
            }
        } else {
            $this->error("❌ API Login failed: " . $result['message']);
        }

        return 0;
    }
}
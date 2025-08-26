<?php
// app/Services/ApiOnlyAuthService.php - FIXED VERSION

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApiOnlyAuthService
{
    private $workingApiService;

    public function __construct(WorkingApiService $workingApiService)
    {
        $this->workingApiService = $workingApiService;
    }

    public function authenticate(string $nik, string $password): array
    {
        Log::info('🔐 ApiOnlyAuthService: Starting authentication', [
            'nik' => $nik,
            'timestamp' => now()
        ]);

        try {
            // GUNAKAN METHOD login YANG SUDAH BEKERJA di WorkingApiService
            $apiResult = $this->workingApiService->login($nik, $password);
            
            if ($apiResult['success']) {
                Log::info('✅ API login successful', ['nik' => $nik]);
                
                // Return dummy user object untuk testing tanpa database
                $user = (object) [
                    'nik' => $nik,
                    'name' => $apiResult['data']['name'] ?? $apiResult['data']['nama'] ?? 'User ' . $nik,
                    'email' => $nik . '@company.com',
                    'role' => 'user'
                ];
                
                return [
                    'success' => true,
                    'message' => 'Authentication successful (API only)',
                    'user' => $user
                ];
            }

            return [
                'success' => false,
                'message' => $apiResult['message'] ?? 'Authentication failed',
                'user' => null
            ];

        } catch (\Exception $e) {
            Log::error('💥 ApiOnlyAuthService exception', [
                'nik' => $nik,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Authentication service error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }
}
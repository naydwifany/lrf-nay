<?php
// app/Services/CustomAuthService.php

namespace App\Services;

interface CustomAuthService
{
    public function authenticate(string $nik, string $password): array|null;
}

// app/Services/ApiOnlyAuthService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ApiOnlyAuthService implements CustomAuthService
{
    private string $apiUrl;
    private array $apiHeaders;

    public function __construct()
    {
        $this->apiUrl = config('services.auth_api.url');
        $this->apiHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function authenticate(string $nik, string $password): array|null
    {
        try {
            // Call external API for authentication
            $response = Http::withHeaders($this->apiHeaders)
                ->timeout(30)
                ->post($this->apiUrl . '/auth/login', [
                    'nik' => $nik,
                    'password' => $password,
                ]);

            if (!$response->successful()) {
                Log::warning('API authentication failed', [
                    'nik' => $nik,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $apiData = $response->json();

            // Validate required fields from API response
            if (!$this->validateApiResponse($apiData)) {
                Log::error('Invalid API response structure', ['response' => $apiData]);
                return null;
            }

            // Update or create user based on API data
            $user = $this->syncUserFromApi($apiData);
            
            if (!$user) {
                Log::error('Failed to sync user from API', ['api_data' => $apiData]);
                return null;
            }

            return [
                'user' => $user,
                'api_data' => $apiData
            ];

        } catch (\Exception $e) {
            Log::error('API authentication error: ' . $e->getMessage(), [
                'nik' => $nik,
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function validateApiResponse(array $data): bool
    {
        // Check if required fields exist in API response
        $required = ['success', 'pegawai'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        if (!$data['success']) {
            return false;
        }

        // Check pegawai data structure
        $pegawaiRequired = ['nik', 'nama', 'jabatan', 'divisi'];
        foreach ($pegawaiRequired as $field) {
            if (!isset($data['pegawai'][$field])) {
                return false;
            }
        }

        return true;
    }

    private function syncUserFromApi(array $apiData): User|null
    {
        try {
            $pegawai = $apiData['pegawai'];
            $atasan = $apiData['atasan'] ?? null;
            
            // Map API data to user attributes
            $userData = [
                'nik' => $pegawai['nik'],
                'name' => $pegawai['nama'],
                'email' => $pegawai['email'] ?? $pegawai['nik'] . '@company.com',
                'pegawai_id' => $pegawai['pegawai_id'] ?? null,
                'department' => $pegawai['department'] ?? null,
                'divisi' => $pegawai['divisi'],
                'seksi' => $pegawai['seksi'] ?? null,
                'subseksi' => $pegawai['subseksi'] ?? null,
                'jabatan' => $pegawai['jabatan'],
                'level' => $pegawai['level'] ?? null,
                'level_tier' => $pegawai['level_tier'] ?? null,
                'supervisor_nik' => $atasan['nik'] ?? null,
                'satker_id' => $pegawai['satker_id'] ?? null,
                'direktorat' => $pegawai['direktorat'] ?? null,
                'unit_name' => $pegawai['unit_name'] ?? null,
                'last_api_sync' => now(),
                'is_active' => true,
                'api_data' => $apiData,
                'role' => $this->determineUserRole($pegawai),
            ];

            // Update or create user
            $user = User::updateOrCreate(
                ['nik' => $pegawai['nik']],
                $userData
            );

            // Sync division hierarchy if needed
            $this->syncDivisionHierarchy($user, $apiData);

            return $user;

        } catch (\Exception $e) {
            Log::error('Error syncing user from API: ' . $e->getMessage(), [
                'api_data' => $apiData,
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function determineUserRole(array $pegawai): string
    {
        $jabatan = strtolower($pegawai['jabatan'] ?? '');
        $level = strtolower($pegawai['level'] ?? '');
        $divisi = strtolower($pegawai['divisi'] ?? '');

        // Legal team roles
        if (str_contains($divisi, 'legal') || str_contains($jabatan, 'legal')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_legal';
            }
            if (str_contains($jabatan, 'admin')) {
                return 'admin_legal';
            }
            if (str_contains($jabatan, 'reviewer')) {
                return 'reviewer_legal';
            }
            return 'legal';
        }

        // Finance team roles
        if (str_contains($divisi, 'finance') || str_contains($divisi, 'keuangan')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_finance';
            }
            return 'finance';
        }

        // Management hierarchy roles
        if (str_contains($jabatan, 'director') || str_contains($jabatan, 'direktur')) {
            return 'director';
        }

        if (str_contains($level, 'general manager') || str_contains($jabatan, 'general manager')) {
            return 'general_manager';
        }

        if (str_contains($level, 'senior manager') || str_contains($jabatan, 'senior manager')) {
            return 'senior_manager';
        }

        if (str_contains($level, 'manager') || str_contains($jabatan, 'manager')) {
            return 'manager';
        }

        if (str_contains($jabatan, 'supervisor') || str_contains($jabatan, 'spv')) {
            return 'supervisor';
        }

        // Default role
        return 'user';
    }

    private function syncDivisionHierarchy(User $user, array $apiData): void
    {
        try {
            // Get division approval group and sync hierarchy
            $divisionGroup = \App\Models\DivisionApprovalGroup::syncFromApi($apiData);
            
            if ($divisionGroup) {
                // Update user's division hierarchy references
                $user->update([
                    'division_manager_nik' => $divisionGroup->manager_nik,
                    'division_senior_manager_nik' => $divisionGroup->senior_manager_nik,
                    'division_general_manager_nik' => $divisionGroup->general_manager_nik,
                    'hierarchy_last_sync' => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error syncing division hierarchy: ' . $e->getMessage(), [
                'user_nik' => $user->nik,
                'api_data' => $apiData
            ]);
        }
    }
}
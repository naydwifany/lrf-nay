<?php
// app/Services/DivisionApprovalService.php - Updated untuk menggunakan WorkingApiService

namespace App\Services;

use App\Models\DivisionApprovalGroup;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DivisionApprovalService
{
    protected $workingApiService;
    protected $adminNik;
    protected $adminPassword;

    public function __construct(WorkingApiService $workingApiService)
    {
        $this->workingApiService = $workingApiService;
        // Gunakan admin credentials untuk sync divisions
        $this->adminNik = config('app.admin_nik', 'admin');
        $this->adminPassword = config('app.admin_password', 'admin123');
    }

    /**
     * Update division approval when user login
     */
    public function updateDivisionApprovalOnLogin(User $user, array $apiUserData = null): void
    {
        try {
            // Get user's division info
            $divisionCode = $user->divisi_code ?? $this->extractDivisionCode($user->divisi);
            $divisionName = $user->divisi;
            
            if (!$divisionName) {
                Log::warning('User has no division assigned', ['nik' => $user->nik]);
                return;
            }

            // Find or create division approval group
            $divisionGroup = DivisionApprovalGroup::firstOrCreate(
                ['division_code' => $divisionCode],
                [
                    'division_name' => $divisionName,
                    'direktorat' => $user->direktorat,
                    'is_active' => true,
                ]
            );

            // Update user's position in division hierarchy
            $this->updateUserPositionInDivision($divisionGroup, $user, $apiUserData);

            Log::info('Division approval updated on login', [
                'nik' => $user->nik,
                'division' => $divisionName,
                'level' => $user->level
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update division approval on login', [
                'nik' => $user->nik,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update user's position in division hierarchy
     */
    protected function updateUserPositionInDivision(DivisionApprovalGroup $divisionGroup, User $user, ?array $apiUserData): void
    {
        $updateData = ['last_sync' => now()];
        $level = strtolower(trim($user->level ?? $this->extractLevelFromJabatan($user->jabatan)));

        // Update based on user level - TAMBAH case untuk supervisor
        switch ($level) {
            case 'supervisor':
                // NEW: Handle supervisor level
                if (Schema::hasColumn('division_approval_groups', 'supervisor_nik')) {
                    if (!$divisionGroup->supervisor_nik || $divisionGroup->supervisor_nik === $user->nik) {
                        $updateData['supervisor_nik'] = $user->nik;
                        $updateData['supervisor_name'] = $user->name;
                    }
                }
                break;

            case 'manager':
            case 'division manager':
                if (!$divisionGroup->manager_nik || $divisionGroup->manager_nik === $user->nik) {
                    $updateData['manager_nik'] = $user->nik;
                    $updateData['manager_name'] = $user->name;
                }
                break;

            case 'senior manager':
            case 'division senior manager':
                if (!$divisionGroup->senior_manager_nik || $divisionGroup->senior_manager_nik === $user->nik) {
                    $updateData['senior_manager_nik'] = $user->nik;
                    $updateData['senior_manager_name'] = $user->name;
                }
                break;

            case 'general manager':
            case 'division general manager':
            case 'gm':
                if (!$divisionGroup->general_manager_nik || $divisionGroup->general_manager_nik === $user->nik) {
                    $updateData['general_manager_nik'] = $user->nik;
                    $updateData['general_manager_name'] = $user->name;
                }
                break;
        }

        // Update supervisor relationships from API data
        if ($apiUserData && isset($apiUserData['atasan'])) {
            $this->updateSupervisorFromApi($divisionGroup, $apiUserData['atasan'], $updateData);
        }

        if (count($updateData) > 1) { // More than just last_sync
            $divisionGroup->update($updateData);
        }
    }

    /**
     * Update supervisor info from API data
     */
    protected function updateSupervisorFromApi(DivisionApprovalGroup $divisionGroup, array $atasanData, array &$updateData): void
    {
        $atasanLevel = strtolower($atasanData['level'] ?? '');
        $atasanNik = $atasanData['nik'] ?? '';
        $atasanName = $atasanData['nama'] ?? '';

        if (!$atasanNik || !$atasanName) return;

        switch ($atasanLevel) {
            case 'manager':
            case 'division manager':
                if (!$divisionGroup->manager_nik) {
                    $updateData['manager_nik'] = $atasanNik;
                    $updateData['manager_name'] = $atasanName;
                }
                break;

            case 'senior manager':
            case 'division senior manager':
                if (!$divisionGroup->senior_manager_nik) {
                    $updateData['senior_manager_nik'] = $atasanNik;
                    $updateData['senior_manager_name'] = $atasanName;
                }
                break;

            case 'general manager':
            case 'division general manager':
            case 'gm':
                if (!$divisionGroup->general_manager_nik) {
                    $updateData['general_manager_nik'] = $atasanNik;
                    $updateData['general_manager_name'] = $atasanName;
                }
                break;
        }
    }

    /**
     * Sync division list from API menggunakan WorkingApiService
     */
    public function syncDivisionsFromApi(): array
    {
        try {
            // 1. Login terlebih dahulu untuk mendapatkan token
            Log::info('Getting admin token for division sync...');
            $loginResult = $this->workingApiService->login($this->adminNik, $this->adminPassword);
            
            if (!$loginResult['success']) {
                throw new \Exception('Failed to get admin token: ' . $loginResult['message']);
            }

            $token = $loginResult['token'];
            Log::info('Admin token obtained successfully');

            // 2. Ambil data divisions menggunakan token
            $divisionsResult = $this->getDivisionsFromApi($token);
            
            if (!$divisionsResult['success']) {
                throw new \Exception('Failed to get divisions: ' . $divisionsResult['message']);
            }

            $divisions = $divisionsResult['data'];
            $synced = 0;
            $errors = [];

            // 3. Process setiap division
            foreach ($divisions as $division) {
                try {
                    $this->createOrUpdateDivisionFromApi($division);
                    $synced++;
                } catch (\Exception $e) {
                    $divisionCode = $division['division_code'] ?? $division['divisicode'] ?? 'unknown';
                    $errors[] = "Division {$divisionCode}: " . $e->getMessage();
                }
            }

            Log::info('Division sync completed', [
                'synced' => $synced,
                'errors' => count($errors)
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            Log::error('Division sync failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get divisions data from API menggunakan token
     */
    protected function getDivisionsFromApi(string $token): array
    {
        try {
            $url = 'http://10.101.0.85/newhris_api/api/divisi.php';
            
            Log::info('Fetching divisions from API', ['url' => $url]);

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            Log::info('Division API Response', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 200)
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
                ];
            }

            $json = $response->json();
            
            // Check response format
            if (isset($json['status']) && $json['status'] === true) {
                return [
                    'success' => true,
                    'data' => $json['data'] ?? []
                ];
            } else if (is_array($json)) {
                // Direct array response
                return [
                    'success' => true,
                    'data' => $json
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $json['message'] ?? 'Invalid response format'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Division API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Division API Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create or update division from API data
     */
    protected function createOrUpdateDivisionFromApi(array $divisionData): void
    {
        // Support berbagai format response API
        $divisionCode = $divisionData['division_code'] 
                       ?? $divisionData['divisicode'] 
                       ?? $divisionData['code'] 
                       ?? '';
        
        $divisionName = $divisionData['division_name'] 
                       ?? $divisionData['divisiname'] 
                       ?? $divisionData['name'] 
                       ?? '';
        
        $direktorat = $divisionData['direktorat'] 
                     ?? $divisionData['directorate'] 
                     ?? '';

        if (!$divisionCode || !$divisionName) {
            throw new \Exception('Invalid division data: missing code or name');
        }

        Log::info('Creating/updating division', [
            'code' => $divisionCode,
            'name' => $divisionName,
            'direktorat' => $direktorat
        ]);

        DivisionApprovalGroup::updateOrCreate(
            ['division_code' => $divisionCode],
            [
                'division_name' => $divisionName,
                'direktorat' => $direktorat,
                'is_active' => true,
                'last_sync' => now()
            ]
        );
    }

    /**
     * Extract division code from division name
     */
    protected function extractDivisionCode(string $divisionName): string
    {
        // Try to get from cache first
        $cacheKey = 'division_code_' . md5($divisionName);
        
        return Cache::remember($cacheKey, 3600, function() use ($divisionName) {
            // Check if exists in database
            $existing = DivisionApprovalGroup::where('division_name', $divisionName)->first();
            if ($existing) {
                return $existing->division_code;
            }

            // Generate code from name
            return 'ECI' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Extract level from jabatan field
     */
    protected function extractLevelFromJabatan(string $jabatan): string
    {
        $jabatan = strtolower($jabatan);
        
        if (str_contains($jabatan, 'general manager') || str_contains($jabatan, 'gm')) {
            return 'General Manager';
        }
        
        if (str_contains($jabatan, 'senior manager')) {
            return 'Senior Manager';
        }
        
        if (str_contains($jabatan, 'manager')) {
            return 'Manager';
        }

        return 'Staff';
    }

    /**
     * Get approval chain for user's division
     */
    public function getApprovalChainForUser(User $user): array
    {
        $divisionCode = $user->divisi_code ?? $this->extractDivisionCode($user->divisi);
        $divisionGroup = DivisionApprovalGroup::where('division_code', $divisionCode)->first();

        if (!$divisionGroup) {
            return [];
        }

        return $divisionGroup->getApprovalChain($user->level)->toArray();
    }

    /**
     * Test connection to division API
     */
    public function testDivisionApiConnection(): array
    {
        try {
            $loginResult = $this->workingApiService->login($this->adminNik, $this->adminPassword);
            
            if (!$loginResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Login failed: ' . $loginResult['message']
                ];
            }

            $divisionsResult = $this->getDivisionsFromApi($loginResult['token']);
            
            return [
                'success' => $divisionsResult['success'],
                'message' => $divisionsResult['success'] 
                    ? 'Connection successful, found ' . count($divisionsResult['data'] ?? []) . ' divisions'
                    : $divisionsResult['message'],
                'division_count' => count($divisionsResult['data'] ?? [])
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
}
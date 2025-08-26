<?php
// app/Services/WorkingApiService.php - Enhanced with authenticate method

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class WorkingApiService
{
    /**
     * Login dengan method yang PASTI berhasil dari quick:test
     */
    public function login(string $nik, string $password): array
    {
        try {
            $url = 'http://10.101.0.85/newhris_api/api/login2.php';
            
            $payload = [
                'username' => $nik,
                'password' => $password,
            ];

            Log::info('Working API Login', ['nik' => $nik]);

            // EXACT method dari quick:test yang berhasil
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode($payload), 'application/json')
                ->post($url);
            
            Log::info('Working API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? false) === true) {
                    return [
                        'status' => 'success',
                        'success' => true,
                        'token' => $json['token'] ?? null,
                        'data' => $json['data'] ?? []
                    ];
                } else {
                    return [
                        'status' => 'failed',
                        'success' => false,
                        'message' => $json['message'] ?? 'Login failed'
                    ];
                }
            }

            return [
                'status' => 'failed',
                'success' => false,
                'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Working API Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'success' => false,
                'message' => 'API Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Authenticate user - required by Login.php
     */
    public function authenticate(string $nik, string $password): array
    {
        Log::info('🔐 WorkingApiService: Starting authentication', [
            'nik' => $nik,
            'timestamp' => now()
        ]);

        try {
            // First try local authentication for existing users
            $localResult = $this->authenticateLocally($nik, $password);
            
            if ($localResult['success']) {
                Log::info('✅ Local authentication successful', ['nik' => $nik]);
                return $localResult;
            }

            Log::info('⚠️ Local authentication failed, trying API', [
                'nik' => $nik,
                'local_message' => $localResult['message'] ?? 'Unknown error'
            ]);

            // Then try API authentication
            $apiResult = $this->login($nik, $password);
            
            if ($apiResult['success']) {
                Log::info('✅ API authentication successful', ['nik' => $nik]);
                
                // Create or update user from API data
                $user = $this->createOrUpdateUser($nik, $apiResult['data'] ?? [], $password);
                
                if ($user) {
                    return [
                        'success' => true,
                        'message' => 'API authentication successful',
                        'user' => $user
                    ];
                }
            }

            Log::error('❌ Both local and API authentication failed', [
                'nik' => $nik,
                'local_message' => $localResult['message'] ?? 'Unknown',
                'api_message' => $apiResult['message'] ?? 'Unknown'
            ]);

            return [
                'success' => false,
                'message' => 'Authentication failed. Please check your credentials.',
                'user' => null
            ];

        } catch (\Exception $e) {
            Log::error('💥 WorkingApiService exception', [
                'nik' => $nik,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Authentication service error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * Create or update user from API data
     */
    private function createOrUpdateUser(string $nik, array $userData, string $password): ?User
    {
        try {
            Log::info('👤 Creating/updating user from API data', ['nik' => $nik]);

            // Find existing user first
            $existingUser = User::where('nik', $nik)->first();
            
            $userDataToUpdate = [
                'name' => $userData['name'] ?? $userData['nama'] ?? ($existingUser->name ?? 'Unknown'),
                'email' => $userData['email'] ?? ($existingUser->email ?? $nik . '@company.com'),
                'password' => Hash::make($password), // Always update password on successful API auth
                'is_active' => true,
            ];

            // Only update role and organizational data if we have new data from API
            if (isset($userData['jabatan']) || isset($userData['divisi']) || $existingUser) {
                $userDataToUpdate = array_merge($userDataToUpdate, [
                    'role' => $this->determineUserRole($userData, $existingUser),
                    'jabatan' => $userData['jabatan'] ?? $existingUser->jabatan ?? null,
                    'divisi' => $userData['divisi'] ?? $existingUser->divisi ?? null,
                    'department' => $userData['department'] ?? $userData['dept'] ?? $existingUser->department ?? null,
                    'direktorat' => $userData['direktorat'] ?? $existingUser->direktorat ?? null,
                    'level' => $userData['level'] ?? $existingUser->level ?? null,
                    'supervisor_nik' => $userData['nik_atasan'] ?? $userData['supervisor_nik'] ?? $existingUser->supervisor_nik ?? null,
                ]);
            }

            // Determine admin access
            $userDataToUpdate['can_access_admin_panel'] = $this->shouldHaveAdminAccess($userDataToUpdate, $existingUser);

            $user = User::updateOrCreate(
                ['nik' => $nik],
                $userDataToUpdate
            );

            Log::info('✅ User created/updated successfully', [
                'nik' => $user->nik,
                'name' => $user->name,
                'role' => $user->role,
                'can_access_admin' => $user->can_access_admin_panel
            ]);

            return $user;

        } catch (\Exception $e) {
            Log::error('💥 Error creating/updating user', [
                'nik' => $nik,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Local authentication fallback
     */
    private function authenticateLocally(string $nik, string $password): array
    {
        try {
            Log::info('🏠 Attempting local authentication', ['nik' => $nik]);
            
            $user = User::where('nik', $nik)->where('is_active', true)->first();
            
            if (!$user) {
                Log::warning('👤 User not found locally', ['nik' => $nik]);
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'user' => null
                ];
            }

            // Check if user has a password set
            if (!$user->password) {
                Log::info('🔑 User has no local password, creating one', ['nik' => $nik]);
                // Set password from current login attempt
                $user->update(['password' => Hash::make($password)]);
                
                return [
                    'success' => true,
                    'message' => 'Local authentication successful (password set)',
                    'user' => $user
                ];
            }

            // Verify password
            if (Hash::check($password, $user->password)) {
                Log::info('✅ Local password verification successful', ['nik' => $nik]);
                return [
                    'success' => true,
                    'message' => 'Local authentication successful',
                    'user' => $user
                ];
            }

            Log::warning('🔐 Local password verification failed', ['nik' => $nik]);
            return [
                'success' => false,
                'message' => 'Invalid password',
                'user' => null
            ];

        } catch (\Exception $e) {
            Log::error('💥 Local authentication exception', [
                'nik' => $nik,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Local authentication error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * Determine user role
     */
    private function determineUserRole(array $userData, ?User $existingUser = null): string
    {
        // If user already has a role and no new organizational data, keep existing role
        if ($existingUser && $existingUser->role && !isset($userData['jabatan'], $userData['divisi'])) {
            return $existingUser->role;
        }

        $jabatan = strtolower($userData['jabatan'] ?? $existingUser->jabatan ?? '');
        $divisi = strtolower($userData['divisi'] ?? $existingUser->divisi ?? '');
        $level = $userData['level'] ?? $existingUser->level ?? 0;

        // Legal roles
        if (str_contains($divisi, 'legal')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_legal';
            }
            if (str_contains($jabatan, 'admin')) {
                return 'admin_legal';
            }
            return 'legal'; // Default legal role
        }

        // Finance roles
        if (str_contains($divisi, 'finance') || str_contains($divisi, 'keuangan')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_finance';
            }
            return 'finance';
        }

        // Management roles by level
        if ($level >= 8) {
            return 'director';
        }
        if ($level >= 6) {
            return 'general_manager';
        }
        if ($level >= 5) {
            return 'senior_manager';
        }
        if ($level >= 4) {
            return 'manager';
        }
        if ($level >= 3) {
            return 'supervisor';
        }

        // Management roles by title
        if (str_contains($jabatan, 'director') || str_contains($jabatan, 'direktur')) {
            return 'director';
        }
        if (str_contains($jabatan, 'general manager') || str_contains($jabatan, 'gm')) {
            return 'general_manager';
        }
        if (str_contains($jabatan, 'senior manager')) {
            return 'senior_manager';
        }
        if (str_contains($jabatan, 'manager')) {
            return 'manager';
        }
        if (str_contains($jabatan, 'supervisor') || str_contains($jabatan, 'spv')) {
            return 'supervisor';
        }

        // Keep existing role if no match found
        return $existingUser->role ?? 'user';
    }

    /**
     * Determine if user should have admin access
     */
    private function shouldHaveAdminAccess(array $userData, ?User $existingUser = null): bool
    {
        $role = $userData['role'] ?? $this->determineUserRole($userData, $existingUser);
        
        return in_array($role, [
            'head_legal',
            'admin_legal', 
            'reviewer_legal',
            'legal',
            'director',
            'general_manager',
            'head_finance'
        ]);
    }

    public function mapLevelToTier(string $level): string
    {
        $levelMapping = [
            '1' => 'Junior',
            '2' => 'Senior', 
            '3' => 'Supervisor',
            '4' => 'Manager',
            '5' => 'Senior Manager',
            '6' => 'General Manager',
            '7' => 'Director',
            '8' => 'Executive'
        ];

        return $levelMapping[$level] ?? 'Staff';
    }

    /**
     * Get employee data dengan token
     */
    public function getPegawaiData(string $nik, string $token): array
    {
        try {
            $url = 'http://10.101.0.85/newhris_api/api/pegawai.php';

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->get($url, [
                    'nik' => $nik
                ]);
            
            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? false) === true) {
                    return [
                        'success' => true,
                        'data' => $json['data'] ?? []
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $json['message'] ?? 'Failed to get employee data'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Working Employee API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Employee API Error: ' . $e->getMessage()
            ];
        }
    }
}
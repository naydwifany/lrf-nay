<?php 
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class HybridAuthService
{
    protected $workingApiService;

    public function __construct(WorkingApiService $workingApiService)
    {
        $this->workingApiService = $workingApiService;
    }

    /**
     * Authenticate user - Try API first, then local
     */
    public function authenticate(string $nik, string $password): array
    {
        try {
            // Step 1: Try API authentication first
            $apiResult = $this->tryApiAuthentication($nik, $password);
            if ($apiResult['success']) {
                return $apiResult;
            }

            // Step 2: Try local authentication for special users
            $localResult = $this->tryLocalAuthentication($nik, $password);
            if ($localResult['success']) {
                return $localResult;
            }

            return [
                'success' => false,
                'message' => 'Invalid credentials. Please check your NIK and password.',
                'user' => null
            ];

        } catch (\Exception $e) {
            Log::error('Hybrid Authentication error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Authentication service error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * Try API authentication
     */
    private function tryApiAuthentication(string $nik, string $password): array
    {
        try {
            // Step 1: Login to get token
            $loginResponse = $this->workingApiService->login($nik, $password);

            if (!$loginResponse['success']) {
                return [
                    'success' => false,
                    'message' => 'API: ' . ($loginResponse['message'] ?? 'Authentication failed'),
                    'user' => null
                ];
            }

            $apiToken = $loginResponse['token'];
            $basicUserData = $loginResponse['data'];

            // Step 2: Get detailed employee data using token
            $employeeData = $this->workingApiService->getPegawaiData($nik, $apiToken);
            
            if (!$employeeData['success']) {
                // If pegawai data fails, use basic data from login
                Log::warning('Failed to get detailed employee data, using basic login data');
                $finalUserData = $basicUserData;
            } else {
                // Merge login data with employee data
                $finalUserData = $employeeData['data'];
                
                // Ensure basic data is present
                if (isset($basicUserData['pegawai'])) {
                    $finalUserData['pegawai']['userid'] = $basicUserData['pegawai']['userid'] ?? null;
                    $finalUserData['pegawai']['username'] = $basicUserData['pegawai']['username'] ?? $nik;
                    $finalUserData['pegawai']['email'] = $basicUserData['pegawai']['email'] ?? $finalUserData['pegawai']['email'] ?? null;
                    $finalUserData['pegawai']['usergroupid'] = $basicUserData['pegawai']['usergroupid'] ?? null;
                }
            }

            // Step 3: Create or update user from API data
            $user = $this->createOrUpdateUserFromApi($finalUserData, $apiToken, $password);

            return [
                'success' => true,
                'message' => 'API authentication successful',
                'user' => $user,
                'auth_method' => 'api'
            ];

        } catch (\Exception $e) {
            Log::error('API Authentication error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'API authentication failed: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * Try local authentication for special users
     */
    private function tryLocalAuthentication(string $nik, string $password): array
    {
        try {
            // Find local user
            $user = User::where('nik', $nik)
                       ->where('is_local_user', true)
                       ->where('is_active', true)
                       ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Local user not found',
                    'user' => null
                ];
            }

            // Check password
            if (!Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid local password',
                    'user' => null
                ];
            }

            // Update last login
            $user->update([
                'last_api_sync' => now(),
                'email_verified_at' => $user->email_verified_at ?? now()
            ]);

            return [
                'success' => true,
                'message' => 'Local authentication successful',
                'user' => $user,
                'auth_method' => 'local'
            ];

        } catch (\Exception $e) {
            Log::error('Local Authentication error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Local authentication error: ' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * Create or update user from API data
     */
    protected function createOrUpdateUserFromApi(array $apiData, string $apiToken, string $password): User
    {
        $pegawai = $apiData['pegawai'];
        $atasan = $apiData['atasan'] ?? null;

        // Determine role based on level and position
        $role = $this->determineUserRole($pegawai);

        // Prepare user data
        $userData = [
            'name' => $pegawai['nama'],
            'email' => $this->generateEmail($pegawai['nik'], $pegawai['nama']),
            'password' => Hash::make($password),
            'pegawai_id' => $pegawai['pegawaiid'],
            'department' => $pegawai['departemen'] ?? null,
            'divisi' => $pegawai['divisi'],
            'seksi' => $pegawai['seksi'] ?? null,
            'subseksi' => $pegawai['subseksi'] ?? null,
            'jabatan' => $pegawai['jabatan'],
            'level' => $pegawai['level'],
            'level_tier' => $this->workingApiService->mapLevelToTier($pegawai['level']),
            'supervisor_nik' => $atasan['nik'] ?? null,
            'satker_id' => $pegawai['satkerid'] ?? null,
            'direktorat' => $pegawai['direktorat'],
            'unit_name' => $pegawai['unitname'],
            'api_token' => $apiToken,
            'last_api_sync' => now(),
            'is_active' => true,
            'is_local_user' => false, // Mark as API user
            'role' => $role,
            'email_verified_at' => now()
        ];

        // Create or update user
        $user = User::updateOrCreate(
            ['nik' => $pegawai['nik']],
            $userData
        );

        return $user;
    }

    /**
     * Determine user role based on API data
     */
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
        if (str_contains($jabatan, 'finance') || str_contains($jabatan, 'keuangan') || str_contains($jabatan, 'accounting')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_finance';
            } else {
                return 'finance';
            }
        }

        // Management levels based on level from API
        if (str_contains($level, 'general manager') || str_contains($jabatan, 'general manager')) {
            return 'general_manager';
        } elseif (str_contains($level, 'senior manager') || str_contains($jabatan, 'senior manager')) {
            return 'senior_manager';
        } elseif (str_contains($level, 'manager') || str_contains($jabatan, 'manager')) {
            return 'manager';
        } elseif (str_contains($level, 'supervisor') || str_contains($jabatan, 'supervisor')) {
            return 'supervisor';
        } elseif (str_contains($level, 'director') || str_contains($jabatan, 'director') || str_contains($jabatan, 'direktur')) {
            return 'director';
        }

        // Default role for regular employees
        return 'user';
    }

    /**
     * Generate email if not provided by API
     */
    protected function generateEmail(string $nik, string $name): string
    {
        $domain = config('app.default_email_domain', 'electroniccity.co.id');
        $cleanName = strtolower(str_replace(' ', '.', $name));
        $cleanName = preg_replace('/[^a-z0-9.]/', '', $cleanName);
        return "{$cleanName}.{$nik}@{$domain}";
    }

    /**
     * Login user and return authentication result
     */
    public function login(string $nik, string $password): array
    {
        $authResult = $this->authenticate($nik, $password);

        if ($authResult['success']) {
            $user = $authResult['user'];
            
            // Check if user account should be active
            if (!$user->is_active) {
                return [
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact administrator.',
                    'user' => null
                ];
            }

            // Log the user in
            Auth::login($user);

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'auth_method' => $authResult['auth_method'],
                'redirect_url' => $this->getRedirectUrl($user)
            ];
        }

        return $authResult;
    }

    /**
     * Get redirect URL based on user role
     */
    protected function getRedirectUrl(User $user): string
    {
        if ($user->isLegal()) {
            return '/admin';
        } else {
            return '/user';
        }
    }

    /**
     * Create local user for special purposes
     */
    public function createLocalUser(array $userData): User
    {
        $userData['is_local_user'] = true;
        $userData['password'] = Hash::make($userData['password']);
        $userData['email_verified_at'] = now();
        $userData['is_active'] = true;

        return User::create($userData);
    }
}
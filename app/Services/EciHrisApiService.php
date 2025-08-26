<?php
// app/Services/EciHrisApiService.php - FIXED VERSION

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EciHrisApiService
{
    private $baseUrl;
    private $apiKey;
    private $timeout;
    private $loginEndpoint;

    public function __construct()
    {
        $this->baseUrl = config('services.eci_hris.base_url', env('ECI_HRIS_API_URL'));
        $this->apiKey = config('services.eci_hris.api_key', env('ECI_HRIS_API_KEY'));
        $this->timeout = config('services.eci_hris.timeout', 30);
        $this->loginEndpoint = config('services.eci_hris.login_endpoint', '/api/login2.php');
    }

    /**
     * Login to ECI HRIS API - FIXED
     */
    public function login(string $nik, string $password): array
    {
        try {
            $url = $this->baseUrl . $this->loginEndpoint;
            
            Log::info('ECI HRIS API Login Attempt', [
                'url' => $url,
                'nik' => $nik,
                'has_password' => !empty($password)
            ]);

            // API mengharapkan parameter 'username' dan 'password'
            $payload = [
                'username' => $nik,
                'password' => $password,
            ];

            Log::info('ECI HRIS API Login Attempt', [
                'url' => $url,
                'username' => $nik,
                'has_password' => !empty($password)
            ]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asForm()
                ->post($url, $payload);

            Log::info('API Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Handle API response format: {"status": true/false, "message": "...", "data": {...}}
                if (isset($data['status']) && $data['status'] === true) {
                    return [
                        'success' => true,
                        'token' => $data['token'] ?? $data['access_token'] ?? 'temp_token_' . time(),
                        'data' => $data['data'] ?? $this->createUserDataFromResponse($data, $nik)
                    ];
                } elseif (isset($data['status']) && $data['status'] === false) {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Login failed'
                    ];
                }
            }

            // Handle non-successful responses
            $responseBody = $response->body();
            $responseData = $response->json();
            
            $errorMessage = 'Login failed';
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } elseif ($response->status() === 401) {
                $errorMessage = 'Invalid credentials';
            } elseif ($response->status() === 400) {
                $errorMessage = 'Bad request - check parameters';
            } elseif ($response->status() === 404) {
                $errorMessage = 'API endpoint not found';
            } elseif ($response->status() >= 500) {
                $errorMessage = 'Server error';
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'debug_info' => [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('ECI HRIS API Login Error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'API connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $url = $this->baseUrl . '/api/test.php';
            
            $response = Http::timeout(10)->get($url);
            
            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Connection OK' : 'Connection failed',
                'response' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get employee data with token - UPDATED for your API structure
     */
    public function getPegawaiData(string $nik, string $token): array
    {
        try {
            $endpoint = '/api/pegawai.php';
            $url = $this->baseUrl . $endpoint;

            $response = Http::withToken($token) // Bearer token
                ->timeout($this->timeout)
                ->get($url, [
                    'nik' => $nik
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === true) {
                    return [
                        'success' => true,
                        'data' => $data['data'] ?? $data
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Failed to get employee data'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'API request failed with status: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('ECI HRIS API Pegawai Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'API connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create user data from login response - UPDATED
     */
    private function createUserDataFromLoginResponse(array $responseData, string $nik): array
    {
        // Login response hanya memberikan basic user info
        // Data lengkap pegawai harus diambil dari endpoint pegawai.php dengan token
        return [
            'pegawai' => [
                'nik' => $nik,
                'nama' => $responseData['data']['nama'] ?? $responseData['nama'] ?? "User {$nik}",
                'username' => $responseData['data']['username'] ?? $nik,
                'email' => $responseData['data']['email'] ?? null,
                'userid' => $responseData['data']['userid'] ?? null,
                'usergroupid' => $responseData['data']['usergroupid'] ?? null,
                // Default values - akan di-update dari endpoint pegawai.php
                'jabatan' => 'Employee',
                'divisi' => 'General',
                'level' => 'Staff',
                'departemen' => null,
                'direktorat' => null,
                'pegawaiid' => null,
                'satkerid' => null,
                'unitname' => null,
                'seksi' => null,
                'subseksi' => null,
            ],
            'atasan' => null // Akan di-update dari endpoint pegawai.php
        ];
    }
    public function getDireksiData(string $token): array
    {
        try {
            $cacheKey = 'eci_hris_direksi_' . md5($token);
            
            return Cache::remember($cacheKey, 3600, function () use ($token) {
                $response = Http::withToken($token)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl . '/api/direksi.php');

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if ($data['success'] ?? false) {
                        return [
                            'success' => true,
                            'data' => $data['data'] ?? []
                        ];
                    }
                }

                return [
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'Failed to get directors data'
                ];
            });

        } catch (\Exception $e) {
            Log::error('ECI HRIS API Direksi Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'API connection failed'
            ];
        }
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

    public function getUserHierarchy(string $nik, string $token): array
    {
        // Return the same data from getPegawaiData for now
        return $this->getPegawaiData($nik, $token);
    }

    public function validateToken(string $token): bool
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($this->baseUrl . '/api/validate.php');

            return $response->successful() && ($response->json()['valid'] ?? false);

        } catch (\Exception $e) {
            Log::error('ECI HRIS API Token Validation Error: ' . $e->getMessage());
            return false;
        }
    }
}
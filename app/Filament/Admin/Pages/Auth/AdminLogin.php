<?php
// app/Filament/Admin/Pages/Auth/AdminLogin.php - FIXED

namespace App\Filament\Admin\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class AdminLogin extends BaseLogin
{
    protected ?string $maxWidth = 'md';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nik')
                    ->label('NIK (Employee ID)')
                    ->placeholder('Enter your company NIK')
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1])
                    ->helperText('Admin panel for Legal team and Management only'),
                
                $this->getPasswordFormComponent()
                    ->placeholder('Enter your company password')
                    ->extraInputAttributes(['tabindex' => 2]),
                
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    public function getCredentialsFromFormData(array $data): array
    {
        return [
            'nik' => $data['nik'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $data = $this->form->getState();
            
            \Log::info('Admin login attempt', ['nik' => $data['nik']]);
            
            // Method 1: Try local authentication first
            $localAuth = Auth::attempt([
                'nik' => $data['nik'],
                'password' => $data['password'],
                'is_active' => true
            ], $data['remember'] ?? false);

            if ($localAuth) {
                $user = Auth::user();
                \Log::info('Admin local auth success', ['user' => $user->nik, 'role' => $user->role]);
                
                // Check admin access
                if (!$this->canAccessAdmin($user)) {
                    Auth::logout();
                    throw ValidationException::withMessages([
                        'data.nik' => 'Access denied. Admin panel is for Legal team and Management only.',
                    ]);
                }

                session()->regenerate();
                return app(LoginResponse::class);
            }

            \Log::info('Admin local auth failed, trying API');

            // Method 2: Try Working API
            try {
                $workingService = new \App\Services\WorkingApiService();
                $loginResult = $workingService->login($data['nik'], $data['password']);
                
                \Log::info('Admin Working API login result', ['success' => $loginResult['success']]);
                
                if ($loginResult['success']) {
                    // Get employee data
                    $employeeResult = $workingService->getPegawaiData($data['nik'], $loginResult['token']);
                    
                    if ($employeeResult['success']) {
                        // Create user dari data API
                        $user = $this->createUserFromApiData($data['nik'], $data['password'], $loginResult, $employeeResult);
                        
                        // Check admin access
                        if (!$this->canAccessAdmin($user)) {
                            throw ValidationException::withMessages([
                                'data.nik' => 'Access denied. Admin panel is for Legal team and Management only.',
                            ]);
                        }

                        Auth::login($user, $data['remember'] ?? false);
                        session()->regenerate();
                        return app(LoginResponse::class);
                    }
                }
            } catch (\Exception $workingException) {
                \Log::error('Admin Working API exception', ['error' => $workingException->getMessage()]);
            }

            // All methods failed
            throw ValidationException::withMessages([
                'data.nik' => 'Invalid NIK or password. Please check your credentials.',
            ]);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            \Log::error('Admin login error', ['error' => $exception->getMessage()]);
            throw ValidationException::withMessages([
                'data.nik' => 'Authentication failed. Please try again.',
            ]);
        }
    }

    public function getTitle(): string
    {
        return 'Admin Login';
    }

    public function getHeading(): string
    {
        return 'Document Flow - Admin Panel';
    }

    public function getSubHeading(): ?string
    {
        return 'For Legal team and Management only';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    private function canAccessAdmin($user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        // Check by role
        $adminRoles = [
            'admin_legal', 'reviewer_legal', 'head_legal', 'legal',
            'general_manager', 'director', 'head_finance', 'senior_manager'
        ];
        
        return $user->can_access_admin_panel || in_array($user->role, $adminRoles);
    }

    protected function createUserFromApiData(string $nik, string $password, array $loginResult, array $employeeResult)
    {
        $pegawai = $employeeResult['data']['pegawai'];
        $atasan = $employeeResult['data']['atasan'] ?? null;
        
        // Determine role
        $role = $this->determineRole($pegawai);
        
        $userData = [
            'name' => $pegawai['nama'],
            'email' => $this->generateEmail($nik, $pegawai['nama']),
            'password' => \Hash::make($password),
            'pegawai_id' => $pegawai['pegawaiid'],
            'department' => $pegawai['departemen'],
            'divisi' => $pegawai['divisi'],
            'seksi' => $pegawai['seksi'],
            'subseksi' => $pegawai['subseksi'],
            'jabatan' => $pegawai['jabatan'],
            'level' => $pegawai['level'],
            'supervisor_nik' => $atasan['nik'] ?? null,
            'satker_id' => $pegawai['satkerid'],
            'direktorat' => $pegawai['direktorat'],
            'unit_name' => $pegawai['unitname'],
            'api_token' => $loginResult['token'],
            'last_api_sync' => now(),
            'is_active' => true,
            'role' => $role,
            'can_access_admin_panel' => $this->shouldHaveAdminAccess($role),
            'api_data' => $employeeResult['data'],
            'email_verified_at' => now()
        ];

        return \App\Models\User::updateOrCreate(['nik' => $nik], $userData);
    }
    
    private function determineRole(array $pegawai): string
    {
        $level = strtolower($pegawai['level'] ?? '');
        $jabatan = strtolower($pegawai['jabatan'] ?? '');
        $divisi = strtolower($pegawai['divisi'] ?? '');

        // Legal roles
        if (str_contains($divisi, 'legal') || str_contains($jabatan, 'legal')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_legal';
            }
            if (str_contains($jabatan, 'admin')) {
                return 'admin_legal';
            }
            return 'reviewer_legal';
        }

        // Finance roles
        if (str_contains($divisi, 'finance') || str_contains($jabatan, 'finance')) {
            if (str_contains($jabatan, 'head') || str_contains($jabatan, 'kepala')) {
                return 'head_finance';
            }
            return 'finance';
        }

        // Management levels
        if (str_contains($level, 'general manager') || str_contains($jabatan, 'general manager')) {
            return 'general_manager';
        } elseif (str_contains($level, 'senior manager') || str_contains($jabatan, 'senior manager')) {
            return 'senior_manager';
        } elseif (str_contains($level, 'manager') || str_contains($jabatan, 'manager')) {
            return 'manager';
        } elseif (str_contains($jabatan, 'supervisor')) {
            return 'supervisor';
        } elseif (str_contains($jabatan, 'director')) {
            return 'director';
        }

        return 'user';
    }

    private function shouldHaveAdminAccess(string $role): bool
    {
        $adminRoles = [
            'admin_legal', 'reviewer_legal', 'head_legal', 'legal',
            'general_manager', 'director', 'head_finance', 'senior_manager'
        ];
        
        return in_array($role, $adminRoles);
    }

    private function generateEmail(string $nik, string $name): string
    {
        // Generate email from name or use NIK
        $cleanName = strtolower(str_replace(' ', '.', $name));
        return $cleanName . '@company.com';
    }
}
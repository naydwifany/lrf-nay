<?php
// app/Filament/User/Pages/Auth/Login.php - Fixed untuk Filament 3.0

namespace App\Filament\User\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Contracts\View\View;

class Login extends BaseLogin
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
                    ->helperText('Use your company NIK and password from HRIS system'),
                
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
            
            \Log::info('Login attempt', ['nik' => $data['nik']]);
            
            // Method 1: Try local authentication first for existing users
            $localAuth = Auth::attempt([
                'nik' => $data['nik'],
                'password' => $data['password'],
                'is_active' => true
            ], $data['remember'] ?? false);

            if ($localAuth) {
                $user = Auth::user();
                \Log::info('Local auth success', ['user' => $user->nik]);
                
                if ($user->isLegal()) {
                    Auth::logout();
                    throw ValidationException::withMessages([
                        'data.nik' => 'Legal team should use the admin panel at /admin',
                    ]);
                }

                session()->regenerate();
                return app(LoginResponse::class);
            }

            \Log::info('Local auth failed, trying API');

            // Method 2: Try Working API (yang pasti berhasil)
            try {
                $workingService = new \App\Services\WorkingApiService();
                $loginResult = $workingService->login($data['nik'], $data['password']);
                
                \Log::info('Working API login result', ['success' => $loginResult['success']]);
                
                if ($loginResult['success']) {
                    // Get employee data
                    $employeeResult = $workingService->getPegawaiData($data['nik'], $loginResult['token']);
                    
                    if ($employeeResult['success']) {
                        // Create user manually dari data API
                        $user = $this->createUserFromApiData($data['nik'], $data['password'], $loginResult, $employeeResult);
                        
                        if ($user->isLegal()) {
                            throw ValidationException::withMessages([
                                'data.nik' => 'Legal team should use the admin panel at /admin',
                            ]);
                        }

                        Auth::login($user, $data['remember'] ?? false);
                        session()->regenerate();
                        return app(LoginResponse::class);
                    }
                }
            } catch (\Exception $workingException) {
                \Log::error('Working API exception', ['error' => $workingException->getMessage()]);
            }

            // All methods failed
            throw ValidationException::withMessages([
                'data.nik' => 'Invalid NIK or password. Please check your credentials.',
            ]);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            \Log::error('Login error', ['error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            throw ValidationException::withMessages([
                'data.nik' => 'Authentication failed. Please try again.',
            ]);
        }
    }

    public function getTitle(): string
    {
        return 'Employee Login';
    }

    public function getHeading(): string
    {
        return 'Document Request System';
    }

    public function getSubHeading(): ?string
    {
        return 'Login with your company NIK and password';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
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
            return str_contains($jabatan, 'head') ? 'head_legal' : 'legal';
        }

        // Finance roles
        if (str_contains($divisi, 'finance') || str_contains($jabatan, 'finance')) {
            return str_contains($jabatan, 'head') ? 'head_finance' : 'finance';
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
    
    protected function getFooter(): ?View
    {
        return null; // Atau return view('filament.user.auth.login-footer') jika ada
    }
}

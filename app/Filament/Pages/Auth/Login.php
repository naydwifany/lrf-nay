<?php
// app/Filament/Pages/Auth/Login.php - SIMPLE VERSION FOR FILAMENT 3.0

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Services\WorkingApiService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nik')
                    ->label('NIK')
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1])
                    ->placeholder('Enter your NIK'),
                    
                $this->getPasswordFormComponent()
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

    public function authenticate(): LoginResponse
    {
        $data = $this->form->getState();
        $nik = $data['nik'];
        $password = $data['password'];
        $remember = $data['remember'] ?? false;

        // Simple panel detection
        $isAdminPanel = str_contains(request()->path(), 'admin');
        $panelName = $isAdminPanel ? 'admin' : 'user';

        Log::info('🚪 Login attempt', [
            'nik' => $nik, 
            'panel' => $panelName,
            'url' => request()->url()
        ]);

        try {
            // Step 1: Try API authentication
            $authService = app(WorkingApiService::class);
            $authResult = $authService->authenticate($nik, $password);

            if (!$authResult['success']) {
                $this->incrementLoginAttempts($nik);
                throw ValidationException::withMessages([
                    'data.nik' => $authResult['message'] ?? 'Authentication failed.',
                ]);
            }

            $user = $authResult['user'];
            if (!$user) {
                throw ValidationException::withMessages([
                    'data.nik' => 'User not found.',
                ]);
            }

            // Step 2: Check panel access
            if ($isAdminPanel && !$this->canAccessAdmin($user)) {
                throw ValidationException::withMessages([
                    'data.nik' => 'Access denied. Admin panel is for Legal team and Management only.',
                ]);
            }

            // Step 3: Login successful
            Auth::login($user, $remember);
            
            $user->update([
                'last_login_at' => now(),
                'login_attempts' => 0
            ]);

            session()->regenerate();

            Log::info('✅ Login successful', [
                'nik' => $nik,
                'name' => $user->name,
                'panel' => $panelName
            ]);

            return app(LoginResponse::class);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('💥 Login error', [
                'nik' => $nik,
                'error' => $e->getMessage()
            ]);
            
            $this->incrementLoginAttempts($nik);
            
            throw ValidationException::withMessages([
                'data.nik' => 'System error. Please try again.',
            ]);
        }
    }

    private function canAccessAdmin($user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Check admin access
        $adminFlag = $user->can_access_admin_panel ?? false;
        $isLegal = in_array($user->role ?? '', ['admin_legal', 'reviewer_legal', 'head_legal', 'legal']);
        $isManagement = in_array($user->role ?? '', ['general_manager', 'director', 'head_finance']);
        
        return $adminFlag || $isLegal || $isManagement;
    }

    private function incrementLoginAttempts(string $nik): void
    {
        try {
            $user = User::where('nik', $nik)->first();
            if ($user && $user->is_active && $user->login_attempts < 10) {
                $user->increment('login_attempts');
                
                if ($user->login_attempts >= 10) {
                    $user->update(['is_active' => false]);
                    Log::warning('Account locked', ['nik' => $nik]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error incrementing login attempts', ['nik' => $nik, 'error' => $e->getMessage()]);
        }
    }

    private function updateDivisionApprovalHierarchy(User $user): void
    {
        try {
            if ($user->divisi && $user->level >= 4) {
                $divisionGroup = \App\Models\DivisionApprovalGroup::firstOrCreate(
                    ['division_name' => $user->divisi],
                    [
                        'division_code' => strtoupper(substr($user->divisi, 0, 3)),
                        'is_active' => true,
                    ]
                );

                $updates = [];
                if ($user->level == 4) {
                    $updates['manager_nik'] = $user->nik;
                    $updates['manager_name'] = $user->name;
                } elseif ($user->level == 5) {
                    $updates['senior_manager_nik'] = $user->nik;
                    $updates['senior_manager_name'] = $user->name;
                } elseif ($user->level >= 6) {
                    $updates['general_manager_nik'] = $user->nik;
                    $updates['general_manager_name'] = $user->name;
                }

                if (!empty($updates)) {
                    $divisionGroup->update($updates);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error updating division hierarchy', [
                'user_nik' => $user->nik,
                'error' => $e->getMessage()
            ]);
        }
    }
}
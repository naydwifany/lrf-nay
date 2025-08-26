<?php
// app/Http/Controllers/Auth/CustomLoginController.php (Updated for API-only)

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ApiOnlyAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CustomLoginController extends Controller
{
    protected $authService;

    public function __construct(ApiOnlyAuthService $authService)
    {
        $this->authService = $authService;
        $this->middleware('guest')->except('logout');
    }

    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login request using API only
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|max:20',
            'password' => 'required|string|min:3',
        ], [
            'nik.required' => 'NIK is required',
            'nik.max' => 'NIK cannot exceed 20 characters',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 3 characters',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('nik'));
        }

        // Rate limiting to prevent brute force
        $key = $this->throttleKey($request);
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'nik' => "Too many login attempts. Please try again in {$seconds} seconds."
            ])->withInput($request->only('nik'));
        }

        $nik = $request->input('nik');
        $password = $request->input('password');

        // Attempt authentication via API
        $authResult = $this->authService->login($nik, $password);

        if ($authResult['success']) {
            $user = $authResult['user'];
            
            // Clear rate limiting on successful login
            RateLimiter::clear($key);
            
            // Regenerate session for security
            $request->session()->regenerate();

            // Log successful login
            \Log::info("User logged in successfully via API", [
                'nik' => $user->nik,
                'name' => $user->name,
                'role' => $user->role,
                'divisi' => $user->divisi,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Show welcome message with user info
            $request->session()->flash('login_success', [
                'message' => 'Welcome back!',
                'user_name' => $user->name,
                'divisi' => $user->divisi,
                'jabatan' => $user->jabatan
            ]);

            // Redirect to appropriate panel
            return redirect()->intended($authResult['redirect_url']);
        }

        // Authentication failed - increment rate limiter
        RateLimiter::hit($key, 300); // 5 minutes lockout

        // Log failed login attempt
        \Log::warning("Failed login attempt", [
            'nik' => $nik,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'error' => $authResult['message']
        ]);

        return back()->withErrors([
            'nik' => $authResult['message'] ?? 'Invalid credentials. Please check your NIK and password.'
        ])->withInput($request->only('nik'));
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        
        if ($user) {
            \Log::info("User logged out", [
                'nik' => $user->nik,
                'name' => $user->name,
                'ip' => $request->ip()
            ]);
        }

        $this->authService->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'You have been logged out successfully.');
    }

    /**
     * API login for mobile/external applications
     */
    public function apiLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|max:20',
            'password' => 'required|string|min:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting for API
        $key = 'api_login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many API login attempts. Please try again later.'
            ], 429);
        }

        $authResult = $this->authService->authenticate(
            $request->input('nik'),
            $request->input('password')
        );

        if ($authResult['success']) {
            $user = $authResult['user'];
            
            // Clear rate limiting
            RateLimiter::clear($key);
            
            // Create API token for the user
            $token = $user->createToken('api-token', ['api-access'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'nik' => $user->nik,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'divisi' => $user->divisi,
                        'jabatan' => $user->jabatan,
                        'level' => $user->level,
                        'direktorat' => $user->direktorat,
                        'unit_name' => $user->unit_name
                    ],
                    'token' => $token,
                    'auth_method' => $authResult['auth_method'],
                    'permissions' => [
                        'can_create_documents' => true,
                        'can_approve' => $user->isSupervisor(),
                        'is_legal' => $user->isLegal(),
                        'is_manager' => $user->isManager(),
                        'is_director' => $user->isDirector()
                    ]
                ]
            ]);
        }

        // Increment rate limiter on failure
        RateLimiter::hit($key, 300);

        return response()->json([
            'success' => false,
            'message' => $authResult['message'] ?? 'Authentication failed'
        ], 401);
    }

    /**
     * API logout
     */
    public function apiLogout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke current token
            $user->currentAccessToken()->delete();
            
            \Log::info("API user logged out", [
                'nik' => $user->nik,
                'name' => $user->name,
                'ip' => $request->ip()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nik' => $user->nik,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'divisi' => $user->divisi,
                'jabatan' => $user->jabatan,
                'level' => $user->level,
                'direktorat' => $user->direktorat,
                'unit_name' => $user->unit_name,
                'is_active' => $user->is_active,
                'last_sync' => $user->last_api_sync,
                'last_login' => $user->last_login_at,
                'permissions' => [
                    'can_create_documents' => true,
                    'can_approve' => $user->isSupervisor(),
                    'is_legal' => $user->isLegal(),
                    'is_manager' => $user->isManager(),
                    'is_director' => $user->isDirector()
                ]
            ]
        ]);
    }

    /**
     * Refresh user data from API
     */
    public function refreshUserData(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $success = $this->authService->refreshUserData($user);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'User data refreshed successfully',
                'data' => $user->fresh()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to refresh user data'
        ], 500);
    }

    /**
     * Get throttle key for rate limiting
     */
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('nik')) . '|' . $request->ip();
    }
}
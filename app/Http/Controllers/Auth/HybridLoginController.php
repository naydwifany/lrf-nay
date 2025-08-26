<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\HybridAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class HybridLoginController extends Controller
{
    protected $authService;

    public function __construct(HybridAuthService $authService)
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
     * Handle login request using Hybrid auth (API + Local)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|max:20',
            'password' => 'required|string|min:3',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('nik'));
        }

        // Rate limiting
        $key = $this->throttleKey($request);
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'nik' => "Too many login attempts. Please try again in {$seconds} seconds."
            ])->withInput($request->only('nik'));
        }

        $nik = $request->input('nik');
        $password = $request->input('password');

        // Attempt authentication via Hybrid service (API + Local)
        $authResult = $this->authService->login($nik, $password);

        if ($authResult['success']) {
            $user = $authResult['user'];
            
            // Clear rate limiting on successful login
            RateLimiter::clear($key);
            
            // Regenerate session for security
            $request->session()->regenerate();

            // Log successful login
            \Log::info("User logged in successfully", [
                'nik' => $user->nik,
                'name' => $user->name,
                'role' => $user->role,
                'auth_method' => $authResult['auth_method'],
                'is_local_user' => $user->is_local_user,
                'ip' => $request->ip()
            ]);

            // Show welcome message
            $authMethodLabel = $authResult['auth_method'] === 'api' ? 'HRIS API' : 'Local Database';
            $request->session()->flash('login_success', [
                'message' => "Welcome back! (Authenticated via {$authMethodLabel})",
                'user_name' => $user->name,
                'divisi' => $user->divisi,
                'jabatan' => $user->jabatan
            ]);

            // Redirect to appropriate panel
            return redirect()->intended($authResult['redirect_url']);
        }

        // Authentication failed
        RateLimiter::hit($key, 300);

        \Log::warning("Failed login attempt", [
            'nik' => $nik,
            'ip' => $request->ip(),
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
                'is_local_user' => $user->is_local_user,
                'ip' => $request->ip()
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'You have been logged out successfully.');
    }

    /**
     * Get throttle key for rate limiting
     */
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('nik')) . '|' . $request->ip();
    }
}
<?php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class CustomAuthenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->routeIs('filament.*')) {
            // Determine which panel and redirect to appropriate login
            if ($request->routeIs('filament.admin.*')) {
                return route('filament.admin.auth.login');
            }
            
            if ($request->routeIs('filament.user.*')) {
                return route('filament.user.auth.login');
            }
        }

        return $request->expectsJson() ? null : route('filament.admin.auth.login');
    }
}
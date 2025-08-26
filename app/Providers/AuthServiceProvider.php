<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\NikEloquentUserProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Register custom NIK-based authentication provider
        Auth::provider('nik_eloquent', function ($app, array $config) {
            return new NikEloquentUserProvider($app['hash'], $config['model']);
        });
    }
}



<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;  // TAMBAH INI
use App\Services\HybridAuthService;
use App\Models\DocumentCommentAttachment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(
            \App\Services\CustomAuthService::class,
            \App\Services\ApiOnlyAuthService::class
        );

        $this->app->singleton(HybridAuthService::class, function ($app) {
            return new HybridAuthService($app->make(\App\Services\WorkingApiService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
         Route::bind('attachment', function ($value) {
            return \App\Models\DocumentCommentAttachment::findOrFail($value);
        });
        
    }
}

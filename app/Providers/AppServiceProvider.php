<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::model('rag', \App\Models\RagEnvironment::class);
        Route::model('document', \App\Models\RagDocument::class);
        Route::model('session', \App\Models\ChatSession::class);
        Route::model('agent', \App\Models\Agent::class);
        Route::model('token', \App\Models\ApiToken::class);
    }
}

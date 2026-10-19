<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
        Route::model('message', \App\Models\ChatMessage::class);
        Route::model('agent', \App\Models\Agent::class);
        Route::model('token', \App\Models\ApiToken::class);

        // Dynamic rate limits driven by security settings (evaluated per request)
        RateLimiter::for('chat', function ($request) {
            $max = class_exists(\App\Models\SecuritySetting::class)
                ? \App\Models\SecuritySetting::int('chat_rate_limit', 30) : 30;
            return Limit::perMinute(max(1, $max))->by($request->ip())->response(function () {
                return response('درخواست‌های بیش از حد مجاز. لطفاً یک دقیقه صبر کنید.', 429);
            });
        });
        RateLimiter::for('api', function ($request) {
            $max = class_exists(\App\Models\SecuritySetting::class)
                ? \App\Models\SecuritySetting::int('api_rate_limit', 60) : 60;
            return Limit::perMinute(max(1, $max))->by($request->ip())->response(function () {
                return response()->json(['error' => 'Too many requests'], 429);
            });
        });
    }
}

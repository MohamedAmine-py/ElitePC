<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        /*
        |----------------------------------------------------------------------
        | API Rate Limiters
        |----------------------------------------------------------------------
        |
        | 'ai-chat' — 30 requests per minute per IP address.
        | Applied to the /api/support/chat route to prevent:
        |   - Gemini API key abuse and cost overruns
        |   - Brute-force or spam attacks on the AI endpoint
        |   - Denial-of-service via rapid-fire requests
        |
        */
        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Too many requests. Please wait before sending another message.',
                    ], 429, $headers);
                });
        });
    }
}

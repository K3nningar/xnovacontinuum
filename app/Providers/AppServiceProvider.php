<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Provides application functionality for AppServiceProvider.
 */

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Defines named request limits, keying unauthenticated flows by IP and game actions by player identity.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('register', fn ($request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('universe-join', fn ($request) => Limit::perHour(5)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('game-action', fn ($request) => Limit::perMinute(30)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('admin-announcement', fn ($request) => Limit::perHour(5)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('multi-account-declaration', fn ($request) => Limit::perHour(5)->by((string) $request->user()?->getAuthIdentifier()));
    }
}

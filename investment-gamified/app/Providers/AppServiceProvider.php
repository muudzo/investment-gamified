<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Register named rate limiters used by the auth routes (and general API
     * traffic). Routes opt in via the `throttle:<name>` middleware alias.
     */
    protected function configureRateLimiting(): void
    {
        // Brute-force protection for login: keyed by email + IP so a single
        // attacker can't spray requests across many IPs against one account
        // and can't dodge the limit by cycling emails from one IP.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            if ($email !== '') {
                return Limit::perMinute(5)->by($email.'|'.$request->ip());
            }

            return Limit::perMinute(5)->by($request->ip());
        });

        // Registration abuse protection: keyed by IP only.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // General-purpose API limiter: authenticated requests are keyed per
        // user, guests fall back to IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}

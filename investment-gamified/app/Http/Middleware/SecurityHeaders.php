<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS only makes sense over an already-secure connection; forcing it on
        // plain HTTP would break local development.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy.
        // Tailwind is now compiled via Vite (no more CDN <script>), so script-src
        // no longer needs 'unsafe-eval'/'unsafe-inline' or the CDN origin. All
        // former inline onclick="" handlers were moved into external JS files
        // wired up with addEventListener, so script-src can be locked to 'self'.
        // style-src keeps 'unsafe-inline' because the senior-mode view relies on
        // inline style="" attributes for layout.
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';");

        return $response;
    }
}

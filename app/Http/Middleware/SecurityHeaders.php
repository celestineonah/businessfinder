<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(self), camera=(), microphone=(), payment=()'
        );
        $response->headers->set(
            'X-Permitted-Cross-Domain-Policies',
            'none'
        );

        if (
            app()->isProduction()
            && str_starts_with(
                strtolower((string) config('app.url')),
                'https://'
            )
        ) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=15552000'
            );
        }

        if ($this->isPrivateSurface($request)) {
            $response->headers->set(
                'X-Robots-Tag',
                'noindex, nofollow'
            );
            $response->headers->set(
                'Cache-Control',
                'private, no-store, max-age=0'
            );
        } elseif ($request->is('search')) {
            $response->headers->set(
                'X-Robots-Tag',
                'noindex, follow'
            );
        }

        return $response;
    }

    private function isPrivateSurface(Request $request): bool
    {
        return $request->is(
            'admin',
            'admin/*',
            'dashboard',
            'dashboard/*',
            'settings',
            'settings/*',
            'login',
            'register',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'confirm-password',
            'email/*',
            'two-factor*',
            'passkey*'
        );
    }
}

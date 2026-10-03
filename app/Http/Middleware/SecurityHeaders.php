<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a baseline set of hardening headers to every response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), browsing-topics=()'
        );

        // Keep the whole site (including PDF CVs) out of search engines unless
        // an admin explicitly enables indexing in Settings.
        if (! $this->indexingAllowed()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Only advertise HSTS when actually served over TLS.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function indexingAllowed(): bool
    {
        try {
            return (bool) Setting::get('search_indexable', false);
        } catch (\Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // La redirection HTTP -> HTTPS est faite par le CDN Hostinger ; l'en-tête est ignoré hors HTTPS.
        if (config('deployment.force_https')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (!config('deployment.indexing')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}

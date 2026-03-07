<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * Middleware that adds security-related HTTP headers (nosniff, DENY framing, HSTS) to every response.
 */
class SecurityHeadersMiddleware implements Middleware
{
    /**
     * Pass the request downstream, then add security headers to the response.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response The downstream response with X-Content-Type-Options, X-Frame-Options, and HSTS headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}

<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * SecurityHeadersMiddleware defines a framework component for this package.
 */
class SecurityHeadersMiddleware implements Middleware
{
    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
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

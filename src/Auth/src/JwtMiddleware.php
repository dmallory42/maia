<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;
use Throwable;

/**
 * JwtMiddleware defines a framework component for this package.
 */
class JwtMiddleware implements Middleware
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param JwtService $jwt Input value.
     * @return void Output value.
     */
    public function __construct(private JwtService $jwt)
    {
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return Response::error('Unauthorized', 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (Throwable) {
            return Response::error('Unauthorized', 401);
        }

        $request = $request->withAttribute('user', $payload);

        return $next($request);
    }
}

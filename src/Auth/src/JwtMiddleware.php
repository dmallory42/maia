<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;
use Throwable;

class JwtMiddleware implements Middleware
{
    public function __construct(private JwtService $jwt)
    {
    }

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

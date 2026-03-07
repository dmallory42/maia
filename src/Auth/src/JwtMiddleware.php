<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;
use Throwable;

/**
 * Middleware that authenticates requests by validating a Bearer JWT token.
 */
class JwtMiddleware implements Middleware
{
    /**
     * Build the middleware with the JWT service used for token verification.
     * @param JwtService $jwt The service that decodes and validates JWT tokens.
     * @return void
     */
    public function __construct(private JwtService $jwt)
    {
    }

    /**
     * Validate the Bearer token and attach the decoded user payload to the request.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response A 401 response if the token is missing or invalid, otherwise the downstream response.
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

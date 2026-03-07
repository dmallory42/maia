<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * Middleware that applies Cross-Origin Resource Sharing (CORS) headers and handles preflight requests.
 */
class CorsMiddleware implements Middleware
{
    /**
     * Configure the CORS policy with allowed origins, methods, and headers.
     * @param array $allowedOrigins Origins permitted to make cross-origin requests (use ['*'] for any).
     * @param array $allowedMethods HTTP methods allowed in cross-origin requests.
     * @param array $allowedHeaders HTTP headers the client is allowed to send.
     * @return void
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-API-Key']
    ) {
    }

    /**
     * Enforce the CORS policy: reject disallowed origins, respond to preflight, and add CORS headers.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response A 403 for denied origins, a 204 for preflight, or the downstream response with CORS headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        if (is_string($origin) && !$this->isOriginAllowed($origin)) {
            return Response::error('CORS origin denied', 403);
        }

        if ($request->method() === 'OPTIONS') {
            return $this->applyHeaders(Response::empty(204), $origin);
        }

        $response = $next($request);

        return $this->applyHeaders($response, $origin);
    }

    /**
     * Check whether the given origin is in the allowed list or wildcarded.
     * @param string $origin The Origin header value from the request.
     * @return bool True if the origin is permitted.
     */
    private function isOriginAllowed(string $origin): bool
    {
        return in_array('*', $this->allowedOrigins, true)
            || in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Attach CORS response headers (Allow-Origin, Allow-Methods, Allow-Headers, Vary).
     * @param Response $response The response to decorate with CORS headers.
     * @param string|null $origin The request's Origin header, or null if absent.
     * @return Response The response with CORS headers applied.
     */
    private function applyHeaders(Response $response, ?string $origin): Response
    {
        $allowedOrigin = in_array('*', $this->allowedOrigins, true) ? '*' : ($origin ?? '');

        if ($allowedOrigin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Vary', 'Origin');
    }
}

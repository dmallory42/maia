<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

class CorsMiddleware implements Middleware
{
    /**
     * @param array<int, string> $allowedOrigins
     * @param array<int, string> $allowedMethods
     * @param array<int, string> $allowedHeaders
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-API-Key']
    ) {
    }

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

    private function isOriginAllowed(string $origin): bool
    {
        return in_array('*', $this->allowedOrigins, true)
            || in_array($origin, $this->allowedOrigins, true);
    }

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

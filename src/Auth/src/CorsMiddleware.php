<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * CorsMiddleware defines a framework component for this package.
 */
class CorsMiddleware implements Middleware
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param array $allowedOrigins Input value.
     * @param array $allowedMethods Input value.
     * @param array $allowedHeaders Input value.
     * @return void Output value.
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-API-Key']
    ) {
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
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
     * Is origin allowed and return bool.
     * @param string $origin Input value.
     * @return bool Output value.
     */
    private function isOriginAllowed(string $origin): bool
    {
        return in_array('*', $this->allowedOrigins, true)
            || in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Apply headers and return Response.
     * @param Response $response Input value.
     * @param string|null $origin Input value.
     * @return Response Output value.
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

<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * Middleware that authenticates requests by matching a header value against allowed API keys.
 */
class ApiKeyMiddleware implements Middleware
{
    /** @var array<int, string> */
    private array $keys;

    /**
     * Configure the middleware with one or more valid API keys and the header to read.
     * @param string|array $keys A single API key or an array of accepted keys.
     * @param string $header The HTTP header name to extract the API key from.
     * @return void
     */
    public function __construct(string|array $keys, private string $header = 'X-API-Key')
    {
        $this->keys = is_array($keys) ? array_values($keys) : [$keys];
    }

    /**
     * Reject the request with 401 if the API key header is missing or invalid.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response A 401 response on failure, otherwise the downstream response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header($this->header);
        if (!is_string($provided) || $provided === '') {
            return Response::error('Unauthorized', 401);
        }

        if (!$this->isValid($provided)) {
            return Response::error('Unauthorized', 401);
        }

        return $next($request);
    }

    /**
     * Check whether the provided key matches any of the accepted keys using constant-time comparison.
     * @param string $provided The API key value from the request header.
     * @return bool True if the key matches an accepted key.
     */
    private function isValid(string $provided): bool
    {
        foreach ($this->keys as $key) {
            if (hash_equals($key, $provided)) {
                return true;
            }
        }

        return false;
    }
}

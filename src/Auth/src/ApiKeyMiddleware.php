<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * ApiKeyMiddleware defines a framework component for this package.
 */
class ApiKeyMiddleware implements Middleware
{
    /** @var array<int, string> */
    private array $keys;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string|array $keys Input value.
     * @param string $header Input value.
     * @return void Output value.
     */
    public function __construct(string|array $keys, private string $header = 'X-API-Key')
    {
        $this->keys = is_array($keys) ? array_values($keys) : [$keys];
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
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
     * Is valid and return bool.
     * @param string $provided Input value.
     * @return bool Output value.
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

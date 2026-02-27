<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

class ApiKeyMiddleware implements Middleware
{
    /** @var array<int, string> */
    private array $keys;

    /**
     * @param string|array<int, string> $keys
     */
    public function __construct(string|array $keys, private string $header = 'X-API-Key')
    {
        $this->keys = is_array($keys) ? array_values($keys) : [$keys];
    }

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

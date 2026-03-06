<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Cache\ResponseCacheStore;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * ResponseCacheMiddleware defines a framework component for this package.
 */
class ResponseCacheMiddleware implements Middleware
{
    /**
     * @var array<int, string>
     */
    private array $cacheableMethods;

    /**
     * @var array<int, int>
     */
    private array $cacheableStatuses;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param ResponseCacheStore $store Input value.
     * @param int $ttlSeconds Input value.
     * @param string $namespace Input value.
     * @param callable|null $keyResolver Input value.
     * @param array<int, string> $cacheableMethods Input value.
     * @param array<int, int> $cacheableStatuses Input value.
     * @return void Output value.
     */
    public function __construct(
        private ResponseCacheStore $store,
        private int $ttlSeconds = 60,
        private string $namespace = 'default',
        private mixed $keyResolver = null,
        array $cacheableMethods = ['GET'],
        array $cacheableStatuses = [200]
    ) {
        $this->cacheableMethods = array_map('strtoupper', $cacheableMethods);
        $this->cacheableStatuses = array_values($cacheableStatuses);
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldReadCache($request)) {
            return $next($request)->withHeader('X-Response-Cache', 'BYPASS');
        }

        $cacheKey = $this->cacheKey($request);
        $cached = $this->store->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $response = $this->decodeResponse($cached);
            if ($response !== null) {
                return $response->withHeader('X-Response-Cache', 'HIT');
            }
        }

        $response = $next($request);
        if (!$this->shouldStoreResponse($response)) {
            return $response->withHeader('X-Response-Cache', 'BYPASS');
        }

        $this->store->set($cacheKey, $this->ttlSeconds, $this->encodeResponse($response));

        return $response->withHeader('X-Response-Cache', 'MISS');
    }

    /**
     * Should read cache and return bool.
     * @param Request $request Input value.
     * @return bool Output value.
     */
    private function shouldReadCache(Request $request): bool
    {
        return in_array($request->method(), $this->cacheableMethods, true) && $this->store->isAvailable();
    }

    /**
     * Should store response and return bool.
     * @param Response $response Input value.
     * @return bool Output value.
     */
    private function shouldStoreResponse(Response $response): bool
    {
        return in_array($response->status(), $this->cacheableStatuses, true);
    }

    /**
     * Cache key and return string.
     * @param Request $request Input value.
     * @return string Output value.
     */
    private function cacheKey(Request $request): string
    {
        if (is_callable($this->keyResolver)) {
            return (string) call_user_func($this->keyResolver, $request, $this->namespace);
        }

        $query = $request->queryParams();
        if ($query !== []) {
            ksort($query);
        }

        return $this->namespace . ':' . $request->path() . '?' . http_build_query($query);
    }

    /**
     * Encode response and return string.
     * @param Response $response Input value.
     * @return string Output value.
     */
    private function encodeResponse(Response $response): string
    {
        $payload = json_encode([
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        return $payload === false ? '' : $payload;
    }

    /**
     * Decode response and return Response|null.
     * @param string $payload Input value.
     * @return Response|null Output value.
     */
    private function decodeResponse(string $payload): ?Response
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        $status = $decoded['status'] ?? null;
        $body = $decoded['body'] ?? null;
        $headers = $decoded['headers'] ?? [];

        if (!is_int($status) || !is_string($body) || !is_array($headers)) {
            return null;
        }

        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            $normalizedHeaders[$name] = $value;
        }

        return Response::make($body, $status, $normalizedHeaders);
    }
}

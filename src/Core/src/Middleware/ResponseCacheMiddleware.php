<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Cache\ResponseCacheStore;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * Middleware that caches eligible HTTP responses and serves cache hits on repeated requests.
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
     * Configure the response cache behavior and storage backend.
     * @param ResponseCacheStore $store Cache backend used to store serialized responses.
     * @param int $ttlSeconds Time to live for cache entries in seconds.
     * @param string $namespace Namespace prefix applied to cache keys.
     * @param callable|null $keyResolver Optional callback(request, namespace) that builds the cache key.
     * @param array<int, string> $cacheableMethods HTTP methods eligible for caching.
     * @param array<int, int> $cacheableStatuses Response status codes eligible for caching.
     * @return void
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
     * Serve a cached response when available, otherwise execute the request and cache the result.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response Cached or freshly generated response with cache status header.
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
     * Check whether the request is eligible for cache lookup.
     * @param Request $request The incoming HTTP request.
     * @return bool True when the request method is cacheable and the store is available.
     */
    private function shouldReadCache(Request $request): bool
    {
        return in_array($request->method(), $this->cacheableMethods, true) && $this->store->isAvailable();
    }

    /**
     * Check whether the response status is eligible for caching.
     * @param Response $response The response produced by the downstream handler.
     * @return bool True when the status code is cacheable.
     */
    private function shouldStoreResponse(Response $response): bool
    {
        return in_array($response->status(), $this->cacheableStatuses, true);
    }

    /**
     * Build the cache key for a request, using a custom resolver when configured.
     * @param Request $request The incoming HTTP request.
     * @return string Cache key string.
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
     * Serialize a response into the payload stored in the cache backend.
     * @param Response $response The response to serialize.
     * @return string Serialized payload, or an empty string on encoding failure.
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
     * Reconstruct a Response object from a cached serialized payload.
     * @param string $payload Serialized cache payload.
     * @return Response|null Reconstructed response, or null if the payload is invalid.
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

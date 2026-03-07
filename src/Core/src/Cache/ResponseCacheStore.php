<?php

declare(strict_types=1);

namespace Maia\Core\Cache;

/**
 * Storage interface for serialized HTTP responses used by response caching middleware.
 */
interface ResponseCacheStore
{
    /**
     * Report whether the cache backend is ready for reads and writes.
     * @return bool True when the store is usable.
     */
    public function isAvailable(): bool;

    /**
     * Retrieve a cached payload by key.
     * @param string $key Cache key.
     * @return string|null Serialized cached response payload, or null if missing.
     */
    public function get(string $key): ?string;

    /**
     * Store a serialized response payload with a TTL.
     * @param string $key Cache key.
     * @param int $ttlSeconds Time to live in seconds.
     * @param string $value Serialized cached response payload.
     * @return void
     */
    public function set(string $key, int $ttlSeconds, string $value): void;
}

<?php

declare(strict_types=1);

namespace Maia\Core\Cache;

/**
 * ResponseCacheStore defines a framework component for this package.
 */
interface ResponseCacheStore
{
    /**
     * Is available and return bool.
     * @return bool Output value.
     */
    public function isAvailable(): bool;

    /**
     * Get and return string|null.
     * @param string $key Input value.
     * @return string|null Output value.
     */
    public function get(string $key): ?string;

    /**
     * Set and return void.
     * @param string $key Input value.
     * @param int $ttlSeconds Input value.
     * @param string $value Input value.
     * @return void Output value.
     */
    public function set(string $key, int $ttlSeconds, string $value): void;
}

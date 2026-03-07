<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Storage interface for per-client rate-limit counters.
 */
interface RateLimitStore
{
    /**
     * Retrieve the current counter state for a namespace/client pair.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @return array{window_start: int, count: int}|null Stored counter state, or null when absent.
     */
    public function get(string $namespace, string $key): ?array;

    /**
     * Persist the counter state for a namespace/client pair.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @param array{window_start: int, count: int} $value Counter state to store.
     * @return void
     */
    public function set(string $namespace, string $key, array $value): void;
}

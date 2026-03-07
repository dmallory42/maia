<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Process-local rate-limit store intended for tests and non-persistent environments.
 */
class InMemoryRateLimitStore implements RateLimitStore
{
    /**
     * @var array<string, array<string, array{window_start: int, count: int}>>
     */
    private array $values = [];

    /**
     * Retrieve the current counter state for a namespace/client pair.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @return array{window_start: int, count: int}|null Stored counter state, or null when absent.
     */
    public function get(string $namespace, string $key): ?array
    {
        return $this->values[$namespace][$key] ?? null;
    }

    /**
     * Persist the counter state for a namespace/client pair.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @param array{window_start: int, count: int} $value Counter state to store.
     * @return void
     */
    public function set(string $namespace, string $key, array $value): void
    {
        $this->values[$namespace][$key] = $value;
    }

    /**
     * Clear all in-memory counters.
     * @return void
     */
    public function clear(): void
    {
        $this->values = [];
    }
}

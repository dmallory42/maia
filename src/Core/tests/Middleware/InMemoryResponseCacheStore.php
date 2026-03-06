<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Middleware;

use Maia\Core\Cache\ResponseCacheStore;

class InMemoryResponseCacheStore implements ResponseCacheStore
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    public function __construct(private bool $available = true)
    {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, int $ttlSeconds, string $value): void
    {
        $this->values[$key] = $value;
    }
}

<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Filesystem-backed rate-limit store that persists counters across PHP requests.
 */
class FilesystemRateLimitStore implements RateLimitStore
{
    /**
     * Store counters in the given directory.
     * @param string $directory Filesystem directory where rate-limit files are stored.
     * @return void
     */
    public function __construct(private string $directory)
    {
    }

    /**
     * Retrieve the current counter state for a namespace/client pair.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @return array{window_start: int, count: int}|null Stored counter state, or null when absent/invalid.
     */
    public function get(string $namespace, string $key): ?array
    {
        $path = $this->pathFor($namespace, $key);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return null;
        }

        $windowStart = $decoded['window_start'] ?? null;
        $count = $decoded['count'] ?? null;

        if (!is_int($windowStart) || !is_int($count)) {
            return null;
        }

        return [
            'window_start' => $windowStart,
            'count' => $count,
        ];
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
        if (!$this->ensureDirectory()) {
            return;
        }

        $payload = json_encode($value);
        if (!is_string($payload)) {
            return;
        }

        file_put_contents($this->pathFor($namespace, $key), $payload, LOCK_EX);
    }

    /**
     * Ensure the backing directory exists.
     * @return bool True when the directory is writable.
     */
    private function ensureDirectory(): bool
    {
        if ($this->directory === '') {
            return false;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            return false;
        }

        return is_writable($this->directory);
    }

    /**
     * Convert a namespace/client pair into a stable on-disk file path.
     * @param string $namespace Logical bucket namespace for one limiter instance.
     * @param string $key Client identifier within that namespace.
     * @return string Absolute file path for the stored counter.
     */
    private function pathFor(string $namespace, string $key): string
    {
        return rtrim($this->directory, '/\\') . '/' . sha1($namespace . ':' . $key) . '.json';
    }
}

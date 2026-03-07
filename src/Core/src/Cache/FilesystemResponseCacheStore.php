<?php

declare(strict_types=1);

namespace Maia\Core\Cache;

/**
 * Filesystem-backed cache store for serialized HTTP responses.
 */
class FilesystemResponseCacheStore implements ResponseCacheStore
{
    /**
     * Use the given directory for storing cache files.
     * @param string $directory Filesystem directory where cache entries are stored.
     * @return void
     */
    public function __construct(private string $directory)
    {
    }

    /**
     * Report whether the cache directory exists and is writable.
     * @return bool True when cache files can be read and written.
     */
    public function isAvailable(): bool
    {
        if ($this->directory === '') {
            return false;
        }

        if (!is_dir($this->directory)) {
            return @mkdir($this->directory, 0777, true);
        }

        return is_writable($this->directory);
    }

    /**
     * Read a cached response payload from disk if it exists and has not expired.
     * @param string $key Cache key.
     * @return string|null Serialized cached response payload, or null if unavailable.
     */
    public function get(string $key): ?string
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload)) {
            @unlink($path);
            return null;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt !== 0 && $expiresAt < time()) {
            @unlink($path);
            return null;
        }

        $value = $payload['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Persist a serialized response payload to disk with an expiration timestamp.
     * @param string $key Cache key.
     * @param int $ttlSeconds Time to live in seconds.
     * @param string $value Serialized cached response payload.
     * @return void
     */
    public function set(string $key, int $ttlSeconds, string $value): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $payload = json_encode([
            'expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'value' => $value,
        ]);

        if ($payload === false) {
            return;
        }

        @file_put_contents($this->pathFor($key), $payload, LOCK_EX);
    }

    /**
     * Convert a cache key into its on-disk file path.
     * @param string $key Cache key.
     * @return string Absolute cache file path.
     */
    private function pathFor(string $key): string
    {
        return rtrim($this->directory, '/\\') . '/' . sha1($key) . '.cache';
    }
}

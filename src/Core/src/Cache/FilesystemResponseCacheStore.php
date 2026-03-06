<?php

declare(strict_types=1);

namespace Maia\Core\Cache;

/**
 * FilesystemResponseCacheStore defines a framework component for this package.
 */
class FilesystemResponseCacheStore implements ResponseCacheStore
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $directory Input value.
     * @return void Output value.
     */
    public function __construct(private string $directory)
    {
    }

    /**
     * Is available and return bool.
     * @return bool Output value.
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
     * Get and return string|null.
     * @param string $key Input value.
     * @return string|null Output value.
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
     * Set and return void.
     * @param string $key Input value.
     * @param int $ttlSeconds Input value.
     * @param string $value Input value.
     * @return void Output value.
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
     * Path for and return string.
     * @param string $key Input value.
     * @return string Output value.
     */
    private function pathFor(string $key): string
    {
        return rtrim($this->directory, '/\\') . '/' . sha1($key) . '.cache';
    }
}

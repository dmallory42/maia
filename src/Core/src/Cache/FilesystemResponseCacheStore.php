<?php

declare(strict_types=1);

namespace Maia\Core\Cache;

use Maia\Core\Logging\Logger;

/**
 * Filesystem-backed cache store for serialized HTTP responses.
 */
class FilesystemResponseCacheStore implements ResponseCacheStore
{
    /**
     * Use the given directory for storing cache files.
     * @param string $directory Filesystem directory where cache entries are stored.
     * @param Logger|null $logger Optional logger used to record filesystem failures.
     * @return void
     */
    public function __construct(
        private string $directory,
        private ?Logger $logger = null
    ) {
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
            $created = $this->runFileOperation(
                action: 'create cache directory',
                operation: fn (): bool => mkdir($this->directory, 0777, true),
                context: ['directory' => $this->directory]
            );

            return $created === true;
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

        $contents = $this->runFileOperation(
            action: 'read cache entry',
            operation: fn (): string|false => file_get_contents($path),
            context: ['path' => $path]
        );
        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload)) {
            $this->deleteFile($path, 'delete invalid cache entry');
            return null;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt !== 0 && $expiresAt < time()) {
            $this->deleteFile($path, 'delete expired cache entry');
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

        $path = $this->pathFor($key);
        $written = $this->runFileOperation(
            action: 'write cache entry',
            operation: fn (): int|false => file_put_contents($path, $payload, LOCK_EX),
            context: ['path' => $path]
        );

        if ($written === false) {
            return;
        }
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

    /**
     * Execute a filesystem operation while capturing warnings for logging.
     * @param string $action Human-readable operation description.
     * @param callable $operation Operation to execute.
     * @param array<string, mixed> $context Structured logging context.
     * @return mixed Operation result, or false when a PHP warning occurred.
     */
    private function runFileOperation(string $action, callable $operation, array $context = []): mixed
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        if ($warning !== null) {
            $this->logFailure($action, $warning, $context);

            return false;
        }

        if ($result === false) {
            $this->logFailure($action, 'Operation returned false.', $context);
        }

        return $result;
    }

    /**
     * Delete a cache file and log failures when they occur.
     * @param string $path Cache file path.
     * @param string $action Human-readable operation description.
     * @return void
     */
    private function deleteFile(string $path, string $action): void
    {
        $this->runFileOperation(
            action: $action,
            operation: static fn (): bool => unlink($path),
            context: ['path' => $path]
        );
    }

    /**
     * Record a filesystem cache failure when a logger is configured.
     * @param string $action Human-readable operation description.
     * @param string $reason Error message captured from PHP or the operation result.
     * @param array<string, mixed> $context Structured logging context.
     * @return void
     */
    private function logFailure(string $action, string $reason, array $context = []): void
    {
        $this->logger?->warning('Filesystem response cache operation failed', [
            'action' => $action,
            'reason' => $reason,
            ...$context,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Logging;

/**
 * Logger defines a framework component for this package.
 */
class Logger
{
    /** @var array<string, int> */
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $path Input value.
     * @param string $level Input value.
     * @param bool $enabled Input value.
     * @return void Output value.
     */
    public function __construct(
        private string $path,
        private string $level = 'info',
        private bool $enabled = true
    ) {
        $this->level = $this->normalizeLevel($level);

        if ($this->enabled) {
            $this->ensureDirectoryExists();
        }
    }

    /**
     * Null and return self.
     * @return self Output value.
     */
    public static function null(): self
    {
        return new self('php://memory', 'error', false);
    }

    /**
     * Stderr and return self.
     * @param string $level Input value.
     * @return self Output value.
     */
    public static function stderr(string $level = 'info'): self
    {
        return new self('php://stderr', $level);
    }

    /**
     * Debug and return void.
     * @param string $message Input value.
     * @param array $context Input value.
     * @return void Output value.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Info and return void.
     * @param string $message Input value.
     * @param array $context Input value.
     * @return void Output value.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Warning and return void.
     * @param string $message Input value.
     * @param array $context Input value.
     * @return void Output value.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Error and return void.
     * @param string $message Input value.
     * @param array $context Input value.
     * @return void Output value.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log and return void.
     * @param string $level Input value.
     * @param string $message Input value.
     * @param array $context Input value.
     * @return void Output value.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $level = $this->normalizeLevel($level);
        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = [
            'timestamp' => gmdate(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $encoded = json_encode($entry);
        if ($encoded === false) {
            return;
        }

        file_put_contents($this->path, $encoded . PHP_EOL, FILE_APPEND);
    }

    /**
     * Should log and return bool.
     * @param string $level Input value.
     * @return bool Output value.
     */
    private function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$this->level];
    }

    /**
     * Normalize level and return string.
     * @param string $level Input value.
     * @return string Output value.
     */
    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);

        return array_key_exists($level, self::LEVELS) ? $level : 'info';
    }

    /**
     * Ensure directory exists and return void.
     * @return void Output value.
     */
    private function ensureDirectoryExists(): void
    {
        if (str_contains($this->path, '://')) {
            return;
        }

        $directory = dirname($this->path);
        if ($directory === '.' || is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }
}

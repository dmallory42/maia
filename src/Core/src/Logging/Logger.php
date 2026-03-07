<?php

declare(strict_types=1);

namespace Maia\Core\Logging;

/**
 * Minimal JSON logger with level filtering and filesystem/stderr targets.
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
     * Configure the logger destination, minimum level, and enabled flag.
     * @param string $path Output target path or stream wrapper.
     * @param string $level Minimum log level to record.
     * @param bool $enabled Whether logging is enabled at all.
     * @return void
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
     * Create a disabled logger that drops all log entries.
     * @return self Null logger instance.
     */
    public static function null(): self
    {
        return new self('php://memory', 'error', false);
    }

    /**
     * Create a logger that writes JSON lines to stderr.
     * @param string $level Minimum log level to record.
     * @return self Stderr-backed logger.
     */
    public static function stderr(string $level = 'info'): self
    {
        return new self('php://stderr', $level);
    }

    /**
     * Log a debug-level message.
     * @param string $message Log message.
     * @param array $context Structured context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info-level message.
     * @param string $message Log message.
     * @param array $context Structured context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning-level message.
     * @param string $message Log message.
     * @param array $context Structured context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log an error-level message.
     * @param string $message Log message.
     * @param array $context Structured context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Serialize and write a log entry if the logger is enabled for the given level.
     * @param string $level Requested log level.
     * @param string $message Log message.
     * @param array $context Structured context data.
     * @return void
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
     * Check whether the requested level meets the configured threshold.
     * @param string $level Requested log level.
     * @return bool True when the entry should be recorded.
     */
    private function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$this->level];
    }

    /**
     * Normalize a level name and fall back to info for unknown values.
     * @param string $level Raw level name.
     * @return string Normalized level name.
     */
    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);

        return array_key_exists($level, self::LEVELS) ? $level : 'info';
    }

    /**
     * Create the parent directory for file-based log paths when needed.
     * @return void
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

<?php

declare(strict_types=1);

namespace Maia\Core\Logging;

class Logger
{
    /** @var array<string, int> */
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

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

    public static function null(): self
    {
        return new self('php://memory', 'error', false);
    }

    public static function stderr(string $level = 'info'): self
    {
        return new self('php://stderr', $level);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
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

    private function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$this->level];
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);

        return array_key_exists($level, self::LEVELS) ? $level : 'info';
    }

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

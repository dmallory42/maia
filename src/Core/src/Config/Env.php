<?php

declare(strict_types=1);

namespace Maia\Core\Config;

/**
 * Env defines a framework component for this package.
 */
class Env
{
    private static array $values = [];

    /**
     * Load and return void.
     * @param string $path Input value.
     * @return void Output value.
     */
    public static function load(string $path): void
    {
        self::$values = [];

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            self::$values[$key] = $value;
        }
    }

    /**
     * Get and return string|null.
     * @param string $key Input value.
     * @param string|null $default Input value.
     * @return string|null Output value.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    /**
     * Reset and return void.
     * @return void Output value.
     */
    public static function reset(): void
    {
        self::$values = [];
    }
}

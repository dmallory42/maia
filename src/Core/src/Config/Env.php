<?php

declare(strict_types=1);

namespace Maia\Core\Config;

/**
 * Parses .env files and provides static access to environment variables.
 */
class Env
{
    private static array $values = [];

    /**
     * Parse a .env file and store its key-value pairs for later retrieval.
     * @param string $path Absolute path to the .env file.
     * @return void
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
     * Retrieve an environment variable by name.
     * @param string $key The environment variable name.
     * @param string|null $default Value returned when the variable is not set.
     * @return string|null The variable value, or the default if not found.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    /**
     * Clear all loaded environment variables.
     * @return void
     */
    public static function reset(): void
    {
        self::$values = [];
    }
}

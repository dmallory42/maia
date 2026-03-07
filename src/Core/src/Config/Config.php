<?php

declare(strict_types=1);

namespace Maia\Core\Config;

/**
 * Loads and provides dot-notation access to PHP configuration files from a directory.
 */
class Config
{
    private array $data = [];

    /**
     * Load all PHP files from the given directory, keyed by filename without extension.
     * @param string $configDir Absolute path to the directory containing configuration PHP files.
     * @return void
     */
    public function __construct(string $configDir)
    {
        if (!is_dir($configDir)) {
            return;
        }

        $files = glob($configDir . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->data[$key] = require $file;
        }
    }

    /**
     * Retrieve a configuration value using dot notation (e.g. "app.debug").
     * @param string $key Dot-delimited key where the first segment is the filename.
     * @param mixed $default Value returned when the key does not exist.
     * @return mixed The configuration value, or the default if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }

            $value = $value[$part];
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Config;

/**
 * Config defines a framework component for this package.
 */
class Config
{
    private array $data = [];

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $configDir Input value.
     * @return void Output value.
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
     * Get and return mixed.
     * @param string $key Input value.
     * @param mixed $default Input value.
     * @return mixed Output value.
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

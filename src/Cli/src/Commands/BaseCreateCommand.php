<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

/**
 * BaseCreateCommand defines a framework component for this package.
 */
abstract class BaseCreateCommand extends Command
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string|null $workspace Input value.
     * @return void Output value.
     */
    public function __construct(protected ?string $workspace = null)
    {
    }

    /**
     * Root path and return string.
     * @return string Output value.
     */
    protected function rootPath(): string
    {
        return $this->workspace ?? getcwd();
    }

    /**
     * Write file and return void.
     * @param string $relativePath Input value.
     * @param string $content Input value.
     * @return void Output value.
     */
    protected function writeFile(string $relativePath, string $content): void
    {
        $path = rtrim($this->rootPath(), '/') . '/' . ltrim($relativePath, '/');
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $content);
    }

    /**
     * Require name and return string|null.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @param string $label Input value.
     * @return string|null Output value.
     */
    protected function requireName(array $args, Output $output, string $label = 'name'): ?string
    {
        $name = $args[0] ?? null;
        if (!is_string($name) || trim($name) === '') {
            $output->error(sprintf('%s is required.', ucfirst($label)));

            return null;
        }

        return trim($name);
    }

    /**
     * Class basename and return string.
     * @param string $name Input value.
     * @return string Output value.
     */
    protected function classBasename(string $name): string
    {
        $name = trim($name);

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    /**
     * Snake case and return string.
     * @param string $value Input value.
     * @return string Output value.
     */
    protected function snakeCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }
}

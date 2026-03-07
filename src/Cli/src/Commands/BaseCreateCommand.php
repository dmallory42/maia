<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

/**
 * Shared scaffolding logic for all create:* commands.
 */
abstract class BaseCreateCommand extends Command
{
    /**
     * Set up the scaffolding command with an optional workspace override.
     * @param string|null $workspace Project root directory; defaults to the current working directory.
     * @return void
     */
    public function __construct(protected ?string $workspace = null)
    {
    }

    /**
     * Resolve the project root directory.
     * @return string Absolute path to the project root.
     */
    protected function rootPath(): string
    {
        return $this->workspace ?? getcwd();
    }

    /**
     * Write content to a file relative to the project root, creating directories as needed.
     * @param string $relativePath File path relative to the project root.
     * @param string $content The file contents to write.
     * @return void
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
     * Extract the first positional argument as a required name, or emit an error.
     * @param array $args CLI arguments passed to the command.
     * @param Output $output Output writer for error reporting.
     * @param string $label Human-readable label used in the error message (e.g. 'controller name').
     * @return string|null The trimmed name, or null if missing.
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
     * Resolve a required name, generate a scaffold, and write it to disk.
     * @param array $args CLI arguments passed to the command.
     * @param Output $output Output writer for status messages.
     * @param string $label Human-readable label used in validation errors.
     * @param callable $factory Callback that receives the class basename and raw name and returns [path, content].
     * @return int Exit code.
     */
    protected function scaffoldFromName(array $args, Output $output, string $label, callable $factory): int
    {
        $name = $this->requireName($args, $output, $label);
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        [$path, $content] = $factory($class, $name);

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }

    /**
     * Strip a trailing .php extension to produce a class name.
     * @param string $name Raw input that may include a .php suffix.
     * @return string The class name without a file extension.
     */
    protected function classBasename(string $name): string
    {
        $name = trim($name);

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    /**
     * Convert a PascalCase or camelCase string to snake_case.
     * @param string $value The string to convert.
     * @return string The snake_cased result.
     */
    protected function snakeCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }
}

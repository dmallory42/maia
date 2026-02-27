<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

abstract class BaseCreateCommand extends Command
{
    public function __construct(protected ?string $workspace = null)
    {
    }

    protected function rootPath(): string
    {
        return $this->workspace ?? getcwd();
    }

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
     * @param array<int, string> $args
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

    protected function classBasename(string $name): string
    {
        $name = trim($name);

        return str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
    }

    protected function snakeCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }
}

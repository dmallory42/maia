<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

class UpCommand extends Command
{
    /** @var callable(string): int */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? static function (string $command): int {
            passthru($command, $exitCode);

            return $exitCode;
        };
    }

    public function name(): string
    {
        return 'up';
    }

    public function description(): string
    {
        return 'Start the Maia development server';
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args, Output $output): int
    {
        $port = $this->resolvePort($args);
        $command = sprintf('php -S localhost:%d -t public public/index.php', $port);

        $output->line($command);

        if (in_array('--dry-run', $args, true)) {
            return 0;
        }

        return (int) ($this->runner)($command);
    }

    /** @param array<int, string> $args */
    private function resolvePort(array $args): int
    {
        $inline = array_values(array_filter($args, static fn (string $arg): bool => str_starts_with($arg, '--port=')));
        if ($inline !== []) {
            return max(1, (int) substr($inline[0], 7));
        }

        $index = array_search('--port', $args, true);
        if ($index !== false && isset($args[$index + 1])) {
            return max(1, (int) $args[$index + 1]);
        }

        return 8000;
    }
}

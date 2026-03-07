<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

/**
 * Starts PHP's built-in development server pointing at the project's public directory.
 */
class UpCommand extends Command
{
    /** @var callable(string): int */
    private $runner;

    /**
     * Set up the server command with an optional shell command runner for testing.
     * @param callable|null $runner Callback that executes a shell command and returns its exit code;
     *     defaults to passthru.
     * @return void
     */
    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? static function (string $command): int {
            passthru($command, $exitCode);

            return $exitCode;
        };
    }

    /**
     * Return the command name.
     * @return string The command identifier.
     */
    public function name(): string
    {
        return 'up';
    }

    /**
     * Return the command description.
     * @return string Short summary for help output.
     */
    public function description(): string
    {
        return 'Start the Maia development server';
    }

    /**
     * Build the server command and launch it, or print it in dry-run mode.
     * @param array $args CLI arguments; supports --port and --dry-run flags.
     * @param Output $output Writer for the constructed command string.
     * @return int Exit code from the server process, or 0 for dry-run.
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

    /**
     * Parse the --port flag from arguments, falling back to 8000.
     * @param array $args CLI arguments to search for a port value.
     * @return int The port number to listen on.
     */
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

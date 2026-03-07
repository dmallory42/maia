<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * Abstract base class for all CLI commands in the Maia framework.
 */
abstract class Command
{
    /**
     * Return the command name used to invoke it from the CLI.
     * @return string The command identifier (e.g. 'new', 'migrate').
     */
    abstract public function name(): string;

    /**
     * Return a short human-readable summary of what the command does.
     * @return string One-line description shown in help output.
     */
    abstract public function description(): string;

    /**
     * Run the command logic and return an exit code.
     * @param array $args Positional and flag arguments passed after the command name.
     * @param Output $output Writer for sending text and JSON responses to the user.
     * @return int Exit code (0 for success, non-zero for failure).
     */
    abstract public function execute(array $args, Output $output): int;

    /**
     * Return the help string showing the command name and its description.
     * @return string Formatted help line for this command.
     */
    public function help(): string
    {
        return sprintf('%s - %s', $this->name(), $this->description());
    }
}

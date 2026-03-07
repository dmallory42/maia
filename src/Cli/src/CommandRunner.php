<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * Dispatches CLI invocations to registered Command instances.
 */
class CommandRunner
{
    /** @var array<string, Command> */
    private array $commands = [];

    private ?Output $lastOutput = null;

    /**
     * Set up the runner with optional real-time output to STDOUT.
     * @param bool $emitOutput Whether to write output directly to STDOUT as it is produced.
     * @return void
     */
    public function __construct(private bool $emitOutput = false)
    {
    }

    /**
     * Register a command so it can be invoked by name.
     * @param Command $command The command instance to make available.
     * @return void
     */
    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * Parse argv, resolve the matching command, and execute it.
     * @param array $argv Raw argument vector (typically from $argv), where index 0 is the script name.
     * @return int Exit code returned by the executed command.
     */
    public function run(array $argv): int
    {
        $tokens = array_slice($argv, 1);

        $json = $this->extractFlag($tokens, '--json');
        $help = $this->extractFlag($tokens, '--help');

        $output = new Output($json, $this->emitOutput);
        $this->lastOutput = $output;

        if ($tokens === []) {
            if ($help) {
                $this->writeGlobalHelp($output);

                return 0;
            }

            $output->error('No command provided');

            return 1;
        }

        $commandName = array_shift($tokens);
        $command = $this->commands[$commandName] ?? null;

        if ($command === null) {
            $output->error(sprintf('Unknown command: %s', $commandName));

            return 1;
        }

        if ($help) {
            $output->line($command->help());

            return 0;
        }

        return $command->execute($tokens, $output);
    }

    /**
     * Return the Output instance from the most recent run, if any.
     * @return Output|null The output captured during the last call to run().
     */
    public function lastOutput(): ?Output
    {
        return $this->lastOutput;
    }

    /**
     * Remove a flag from the token list and report whether it was present.
     * @param array& $tokens Argument tokens to search; modified in place if the flag is found.
     * @param string $flag The flag string to look for (e.g. '--json').
     * @return bool True if the flag was found and removed.
     */
    private function extractFlag(array &$tokens, string $flag): bool
    {
        $index = array_search($flag, $tokens, true);
        if ($index === false) {
            return false;
        }

        unset($tokens[$index]);
        $tokens = array_values($tokens);

        return true;
    }

    /**
     * Print a sorted list of all registered commands with their descriptions.
     * @param Output $output Destination for the help text.
     * @return void
     */
    private function writeGlobalHelp(Output $output): void
    {
        if ($this->commands === []) {
            $output->line('No commands registered.');

            return;
        }

        ksort($this->commands);

        foreach ($this->commands as $command) {
            $output->line($command->help());
        }
    }
}

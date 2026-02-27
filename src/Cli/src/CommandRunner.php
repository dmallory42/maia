<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * CommandRunner defines a framework component for this package.
 */
class CommandRunner
{
    /** @var array<string, Command> */
    private array $commands = [];

    private ?Output $lastOutput = null;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param bool $emitOutput Input value.
     * @return void Output value.
     */
    public function __construct(private bool $emitOutput = false)
    {
    }

    /**
     * Register and return void.
     * @param Command $command Input value.
     * @return void Output value.
     */
    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * Run and return int.
     * @param array $argv Input value.
     * @return int Output value.
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
     * Last output and return Output|null.
     * @return Output|null Output value.
     */
    public function lastOutput(): ?Output
    {
        return $this->lastOutput;
    }

    /**
     * Extract flag and return bool.
     * @param array& $tokens Input value.
     * @param string $flag Input value.
     * @return bool Output value.
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
     * Write global help and return void.
     * @param Output $output Input value.
     * @return void Output value.
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

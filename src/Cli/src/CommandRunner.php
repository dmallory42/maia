<?php

declare(strict_types=1);

namespace Maia\Cli;

class CommandRunner
{
    /** @var array<string, Command> */
    private array $commands = [];

    private ?Output $lastOutput = null;

    public function register(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $tokens = array_slice($argv, 1);

        $json = $this->extractFlag($tokens, '--json');
        $help = $this->extractFlag($tokens, '--help');

        $output = new Output($json);
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

    public function lastOutput(): ?Output
    {
        return $this->lastOutput;
    }

    /**
     * @param array<int, string> $tokens
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

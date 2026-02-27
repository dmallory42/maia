<?php

declare(strict_types=1);

namespace Maia\Cli;

abstract class Command
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @param array<int, string> $args
     */
    abstract public function execute(array $args, Output $output): int;

    public function help(): string
    {
        return sprintf('%s - %s', $this->name(), $this->description());
    }
}

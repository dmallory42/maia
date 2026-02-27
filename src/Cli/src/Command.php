<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * Command defines a framework component for this package.
 */
abstract class Command
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    abstract public function name(): string;

    /**
     * Description and return string.
     * @return string Output value.
     */
    abstract public function description(): string;

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    abstract public function execute(array $args, Output $output): int;

    /**
     * Help and return string.
     * @return string Output value.
     */
    public function help(): string
    {
        return sprintf('%s - %s', $this->name(), $this->description());
    }
}

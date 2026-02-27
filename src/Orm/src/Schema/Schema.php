<?php

declare(strict_types=1);

namespace Maia\Orm\Schema;

use Maia\Orm\Connection;

/**
 * Schema defines a framework component for this package.
 */
class Schema
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param Connection $connection Input value.
     * @return void Output value.
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Create and return void.
     * @param string $table Input value.
     * @param callable $callback Input value.
     * @return void Output value.
     */
    public function create(string $table, callable $callback): void
    {
        $definition = new Table();
        $callback($definition);

        $this->connection->execute($definition->toCreateSql($table));
    }

    /**
     * Drop and return void.
     * @param string $table Input value.
     * @return void Output value.
     */
    public function drop(string $table): void
    {
        $this->connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }
}

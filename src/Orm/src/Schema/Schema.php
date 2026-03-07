<?php

declare(strict_types=1);

namespace Maia\Orm\Schema;

use Maia\Orm\Connection;

/**
 * Small schema builder facade for creating and dropping tables through the connection layer.
 */
class Schema
{
    /**
     * Bind the schema builder to a database connection.
     * @param Connection $connection Database connection used to execute schema SQL.
     * @return void
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Create a table by passing a Table definition object to the callback.
     * @param string $table Table name to create.
     * @param callable $callback Callback that configures the Table definition.
     * @return void
     */
    public function create(string $table, callable $callback): void
    {
        $definition = new Table();
        $callback($definition);

        $this->connection->execute($definition->toCreateSql($table));
    }

    /**
     * Drop a table if it exists.
     * @param string $table Table name to drop.
     * @return void
     */
    public function drop(string $table): void
    {
        $this->connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }
}

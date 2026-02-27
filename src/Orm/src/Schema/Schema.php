<?php

declare(strict_types=1);

namespace Maia\Orm\Schema;

use Maia\Orm\Connection;

class Schema
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $table, callable $callback): void
    {
        $definition = new Table();
        $callback($definition);

        $this->connection->execute($definition->toCreateSql($table));
    }

    public function drop(string $table): void
    {
        $this->connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }
}

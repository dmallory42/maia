<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Orm\Connection;

/**
 * Shared connection and migration path resolution for migration commands.
 */
abstract class BaseMigrateCommand extends Command
{
    /**
     * Configure the command with optional connection and migration directory overrides.
     * @param Connection|null $connection Database connection to use; defaults to the local SQLite database.
     * @param string|null $migrationDir Directory containing migration files; defaults to database/migrations.
     * @return void
     */
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    /**
     * Resolve the database connection used by the migration command.
     * @return Connection Database connection.
     */
    protected function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    /**
     * Resolve the migration directory path used by the command.
     * @return string Absolute migration directory path.
     */
    protected function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

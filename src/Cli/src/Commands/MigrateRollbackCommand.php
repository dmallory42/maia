<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

/**
 * CLI command that rolls back the most recent migration batch.
 */
class MigrateRollbackCommand extends Command
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
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'migrate:rollback';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Rollback the last migration batch';
    }

    /**
     * Roll back the latest migration batch and report the number of files reverted.
     * @param array $args CLI arguments (unused).
     * @param Output $output Output writer for text or JSON status reporting.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        $migrator = new Migrator($this->connection(), $this->migrationDirectory());
        $count = $migrator->rollback();

        if ($output->isJson()) {
            $output->json(['rolled_back' => $count]);
        } else {
            $output->line(sprintf('Rolled back %d file(s).', $count));
        }

        return 0;
    }

    /**
     * Resolve the database connection used for migration bookkeeping.
     * @return Connection Database connection.
     */
    private function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    /**
     * Resolve the migration directory to read rollback files from.
     * @return string Absolute migration directory path.
     */
    private function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

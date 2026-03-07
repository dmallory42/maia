<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

/**
 * Runs all pending database migrations in order.
 */
class MigrateCommand extends Command
{
    /**
     * Set up the migrate command with optional database connection and migration directory.
     * @param Connection|null $connection Database connection; defaults to a local SQLite file.
     * @param string|null $migrationDir Path to the migrations directory; defaults to database/migrations.
     * @return void
     */
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    /**
     * Return the command name.
     * @return string The command identifier.
     */
    public function name(): string
    {
        return 'migrate';
    }

    /**
     * Return the command description.
     * @return string Short summary for help output.
     */
    public function description(): string
    {
        return 'Run pending migrations';
    }

    /**
     * Run all pending migrations and report how many were applied.
     * @param array $args CLI arguments (unused).
     * @param Output $output Writer for migration results.
     * @return int Exit code (always 0).
     */
    public function execute(array $args, Output $output): int
    {
        $migrator = new Migrator($this->connection(), $this->migrationDirectory());
        $count = $migrator->migrate();

        if ($output->isJson()) {
            $output->json(['migrated' => $count]);
        } else {
            $output->line(sprintf('Migrated %d file(s).', $count));
        }

        return 0;
    }

    /**
     * Resolve the database connection, falling back to a local SQLite file.
     * @return Connection The active database connection.
     */
    protected function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    /**
     * Resolve the migrations directory path.
     * @return string Absolute path to the directory containing migration files.
     */
    protected function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

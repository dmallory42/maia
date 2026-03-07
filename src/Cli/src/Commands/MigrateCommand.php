<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;
use Maia\Orm\Migrator;

/**
 * Runs all pending database migrations in order.
 */
class MigrateCommand extends BaseMigrateCommand
{
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
}

<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;
use Maia\Orm\Migrator;

/**
 * CLI command that reports which migrations have run and which are still pending.
 */
class MigrateStatusCommand extends BaseMigrateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'migrate:status';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Show migration status';
    }

    /**
     * Show migration status for every migration file in the configured directory.
     * @param array $args CLI arguments (unused).
     * @param Output $output Output writer for text or JSON status reporting.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        $connection = $this->connection();
        $directory = $this->migrationDirectory();

        // Ensure migrations table exists before querying status.
        new Migrator($connection, $directory);

        $ran = array_column($connection->query('SELECT migration FROM migrations'), 'migration');
        $ranLookup = array_flip($ran);

        $files = glob(rtrim($directory, '/') . '/*.php');
        $files = $files === false ? [] : $files;
        sort($files);

        $status = [];
        foreach ($files as $file) {
            $name = basename($file);
            $status[] = [
                'migration' => $name,
                'ran' => isset($ranLookup[$name]),
            ];
        }

        if ($output->isJson()) {
            $output->json(['migrations' => $status]);

            return 0;
        }

        foreach ($status as $item) {
            $output->line(sprintf('[%s] %s', $item['ran'] ? 'ran' : 'pending', $item['migration']));
        }

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

/**
 * MigrateStatusCommand defines a framework component for this package.
 */
class MigrateStatusCommand extends Command
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param Connection|null $connection Input value.
     * @param string|null $migrationDir Input value.
     * @return void Output value.
     */
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'migrate:status';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Show migration status';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $connection = $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
        $directory = $this->migrationDir ?? (getcwd() . '/database/migrations');

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

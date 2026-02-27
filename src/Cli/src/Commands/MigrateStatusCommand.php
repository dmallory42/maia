<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

class MigrateStatusCommand extends Command
{
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show migration status';
    }

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

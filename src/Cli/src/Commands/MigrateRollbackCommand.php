<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

class MigrateRollbackCommand extends Command
{
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Rollback the last migration batch';
    }

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

    private function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    private function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

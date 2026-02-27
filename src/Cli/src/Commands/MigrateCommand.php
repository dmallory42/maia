<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

class MigrateCommand extends Command
{
    public function __construct(
        private ?Connection $connection = null,
        private ?string $migrationDir = null
    ) {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run pending migrations';
    }

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

    protected function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    protected function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

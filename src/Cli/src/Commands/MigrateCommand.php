<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use Maia\Orm\Migrator;

/**
 * MigrateCommand defines a framework component for this package.
 */
class MigrateCommand extends Command
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
        return 'migrate';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Run pending migrations';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
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
     * Connection and return Connection.
     * @return Connection Output value.
     */
    protected function connection(): Connection
    {
        return $this->connection ?? new Connection('sqlite:' . getcwd() . '/database/database.sqlite');
    }

    /**
     * Migration directory and return string.
     * @return string Output value.
     */
    protected function migrationDirectory(): string
    {
        return $this->migrationDir ?? (getcwd() . '/database/migrations');
    }
}

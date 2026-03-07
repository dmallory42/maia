<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Schema\Schema;
use RuntimeException;

/**
 * Applies and rolls back migration files while tracking execution history in the migrations table.
 */
class Migrator
{
    private Schema $schema;

    /**
     * Configure the migrator with a database connection and migration directory.
     * @param Connection $connection Database connection used for schema changes and migration bookkeeping.
     * @param string $migrationDir Directory containing migration PHP files.
     * @return void
     */
    public function __construct(
        private Connection $connection,
        private string $migrationDir
    ) {
        $this->schema = new Schema($this->connection);
        $this->ensureRepository();
    }

    /**
     * Run all pending migration files in order and record them in the repository table.
     * @return int Number of migration files executed.
     */
    public function migrate(): int
    {
        $executed = array_column($this->connection->query('SELECT migration FROM migrations'), 'migration');
        $executed = array_flip($executed);

        $pendingFiles = [];
        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (!isset($executed[$name])) {
                $pendingFiles[] = $file;
            }
        }

        if ($pendingFiles === []) {
            return 0;
        }

        $batch = $this->nextBatchNumber();
        $count = 0;

        foreach ($pendingFiles as $file) {
            $migration = $this->loadMigration($file);
            $migration->up($this->schema);

            $this->connection->execute(
                'INSERT INTO migrations (migration, batch, migrated_at) VALUES (?, ?, ?)',
                [basename($file), $batch, gmdate(DATE_ATOM)]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Roll back the most recently executed migration batch.
     * @return int Number of migration files rolled back.
     */
    public function rollback(): int
    {
        $rows = $this->connection->query('SELECT MAX(batch) AS batch FROM migrations');
        $lastBatch = $rows[0]['batch'] ?? null;

        if ($lastBatch === null) {
            return 0;
        }

        $migrations = $this->connection->query(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC',
            [(int) $lastBatch]
        );

        $count = 0;

        foreach ($migrations as $row) {
            $name = (string) $row['migration'];
            $file = rtrim($this->migrationDir, '/') . '/' . $name;
            if (!file_exists($file)) {
                continue;
            }

            $migration = $this->loadMigration($file);
            $migration->down($this->schema);

            $this->connection->execute('DELETE FROM migrations WHERE migration = ?', [$name]);
            $count++;
        }

        return $count;
    }

    /**
     * Ensure the migrations bookkeeping table exists.
     * @return void
     */
    private function ensureRepository(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                migrated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Return the sorted list of migration PHP files in the migration directory.
     * @return array Migration file paths.
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationDir)) {
            return [];
        }

        $files = glob(rtrim($this->migrationDir, '/') . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * Require a migration file and assert that it returns a Migration instance.
     * @param string $file Absolute migration file path.
     * @return Migration Loaded migration instance.
     */
    private function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Migration file [%s] must return a Migration instance.', $file));
        }

        return $migration;
    }

    /**
     * Determine the next migration batch number.
     * @return int Next batch number.
     */
    private function nextBatchNumber(): int
    {
        $rows = $this->connection->query('SELECT MAX(batch) AS batch FROM migrations');

        return ((int) ($rows[0]['batch'] ?? 0)) + 1;
    }
}

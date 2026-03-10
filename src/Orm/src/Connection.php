<?php

declare(strict_types=1);

namespace Maia\Orm;

use PDO;
use PDOStatement;

/**
 * PDO database connection wrapper with automatic error handling and parameter binding.
 */
class Connection
{
    private PDO $pdo;

    /**
     * Open a PDO connection with exception-mode and associative fetches enabled by default.
     * @param string $dsn PDO data source name (e.g. "sqlite::memory:" or "mysql:host=...").
     * @param string|null $username Database username, if required by the driver.
     * @param string|null $password Database password, if required by the driver.
     * @param array $options Additional PDO driver options merged over the defaults.
     * @return void
     */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = []
    ) {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options + $defaults);
    }

    /**
     * Create a SQLite connection, optionally applying PRAGMA settings.
     * @param string $path Filesystem path to the SQLite database or ":memory:" for in-memory.
     * @param array<string, bool|int|float|string|null> $pragmas Map of PRAGMA names to values to execute on connect.
     * @param array $options Additional PDO driver options.
     * @return self The configured SQLite connection.
     */
    public static function sqlite(string $path = ':memory:', array $pragmas = [], array $options = []): self
    {
        $dsn = $path === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $path;
        $connection = new self($dsn, null, null, $options);

        if ($pragmas !== []) {
            $connection->configureSqlite($pragmas);
        }

        return $connection;
    }

    /**
     * Execute a SELECT query and return all matching rows as associative arrays.
     * @param string $sql The SQL statement, optionally with parameter placeholders.
     * @param array $params Positional or named parameter values to bind.
     * @return array List of associative-array rows.
     */
    public function query(string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Execute a non-SELECT statement (INSERT, UPDATE, DELETE, DDL) and return the affected row count.
     * @param string $sql The SQL statement, optionally with parameter placeholders.
     * @param array $params Positional or named parameter values to bind.
     * @return int Number of rows affected by the statement.
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->rowCount();
    }

    /**
     * Return the ID of the last inserted row.
     * @return string The last auto-generated insert ID as a string.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Apply PRAGMA statements to a SQLite connection; no-op for other drivers.
     * @param array<string, bool|int|float|string|null> $pragmas Map of PRAGMA names to their desired values.
     * @return void
     */
    public function configureSqlite(array $pragmas): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            return;
        }

        foreach ($pragmas as $name => $value) {
            $pragma = trim($name);
            if ($pragma === '') {
                continue;
            }

            $this->pdo->exec(sprintf('PRAGMA %s = %s', $pragma, $this->quotePragmaValue($value)));
        }
    }

    /**
     * Prepare a statement, bind parameters with automatic type detection, and execute it.
     * @param string $sql The SQL statement with optional placeholders.
     * @param array $params Positional or named parameter values to bind.
     * @return PDOStatement The executed statement ready for fetching or row-count inspection.
     */
    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : (string) $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($parameter, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * Convert a PHP value into a SQLite-safe PRAGMA literal (ON/OFF, numeric, quoted string, or NULL).
     * @param bool|int|float|string|null $value The PHP value to convert.
     * @return string The SQL-safe literal representation.
     */
    private function quotePragmaValue(bool|int|float|string|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'ON' : 'OFF';
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->pdo->quote($value);
    }
}

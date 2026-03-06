<?php

declare(strict_types=1);

namespace Maia\Orm;

use PDO;
use PDOStatement;

/**
 * Connection defines a framework component for this package.
 */
class Connection
{
    private PDO $pdo;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $dsn Input value.
     * @param string|null $username Input value.
     * @param string|null $password Input value.
     * @param array $options Input value.
     * @return void Output value.
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
     * Sqlite and return self.
     * @param string $path Input value.
     * @param array<string, bool|int|float|string|null> $pragmas Input value.
     * @param array $options Input value.
     * @return self Output value.
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
     * Query and return array.
     * @param string $sql Input value.
     * @param array $params Input value.
     * @return array Output value.
     */
    public function query(string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Execute and return int.
     * @param string $sql Input value.
     * @param array $params Input value.
     * @return int Output value.
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->rowCount();
    }

    /**
     * Last insert id and return string.
     * @return string Output value.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Pdo and return PDO.
     * @return PDO Output value.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Configure sqlite and return void.
     * @param array<string, bool|int|float|string|null> $pragmas Input value.
     * @return void Output value.
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
     * Prepare and execute and return PDOStatement.
     * @param string $sql Input value.
     * @param array $params Input value.
     * @return PDOStatement Output value.
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
     * Quote pragma value and return string.
     * @param bool|int|float|string|null $value Input value.
     * @return string Output value.
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

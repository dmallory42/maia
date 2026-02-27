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
     * Prepare and execute and return PDOStatement.
     * @param string $sql Input value.
     * @param array $params Input value.
     * @return PDOStatement Output value.
     */
    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}

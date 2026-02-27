<?php

declare(strict_types=1);

namespace Maia\Orm;

/**
 * QueryBuilder defines a framework component for this package.
 */
class QueryBuilder
{
    /** @var array<int, string> */
    private array $columns = ['*'];

    /**
     * @var array<int, array{
     *     type: 'basic'|'in',
     *     column: string,
     *     operator?: string,
     *     value?: mixed,
     *     values?: array<int, mixed>
     * }>
     */
    private array $wheres = [];

    /** @var array<int, array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    /** @var array<int, string> */
    private array $relations = [];

    private ?string $modelClass = null;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $table Input value.
     * @param Connection $connection Input value.
     * @return void Output value.
     */
    private function __construct(
        private string $table,
        private Connection $connection
    ) {
    }

    /**
     * Table and return self.
     * @param string $table Input value.
     * @param Connection $connection Input value.
     * @return self Output value.
     */
    public static function table(string $table, Connection $connection): self
    {
        return new self($table, $connection);
    }

    /**
     * Select and return self.
     * @param string... $columns Input value.
     * @return self Output value.
     */
    public function select(string ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Where and return self.
     * @param string $column Input value.
     * @param mixed $value Input value.
     * @param string $operator Input value.
     * @return self Output value.
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Where in and return self.
     * @param string $column Input value.
     * @param array $values Input value.
     * @return self Output value.
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = [
                'type' => 'in',
                'column' => $column,
                'values' => [],
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
        ];

        return $this;
    }

    /**
     * Order by and return self.
     * @param string $column Input value.
     * @param string $direction Input value.
     * @return self Output value.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /**
     * Limit and return self.
     * @param int $limit Input value.
     * @return self Output value.
     */
    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);

        return $this;
    }

    /**
     * Offset and return self.
     * @param int $offset Input value.
     * @return self Output value.
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    /**
     * With and return self.
     * @param string... $relations Input value.
     * @return self Output value.
     */
    public function with(string ...$relations): self
    {
        $this->relations = array_values(array_unique(array_merge($this->relations, $relations)));

        return $this;
    }

    /**
     * For model and return self.
     * @param string $modelClass Input value.
     * @return self Output value.
     */
    public function forModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Get and return array.
     * @return array Output value.
     */
    public function get(): array
    {
        [$sql, $params] = $this->compileSelect();
        $rows = $this->connection->query($sql, $params);

        if ($this->modelClass === null) {
            return $rows;
        }

        return $this->hydrateModels($rows);
    }

    /**
     * First and return mixed.
     * @return mixed Output value.
     */
    public function first(): mixed
    {
        $clone = clone $this;
        $clone->limit(1);
        $rows = $clone->get();

        return $rows[0] ?? null;
    }

    /**
     * Find and return mixed.
     * @param int|string $id Input value.
     * @return mixed Output value.
     */
    public function find(int|string $id): mixed
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Count and return int.
     * @return int Output value.
     */
    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) AS aggregate'];
        $clone->orders = [];
        $clone->limitValue = null;
        $clone->offsetValue = null;

        [$sql, $params] = $clone->compileSelect();
        $rows = $this->connection->query($sql, $params);

        return (int) ($rows[0]['aggregate'] ?? 0);
    }

    /**
     * Insert and return int.
     * @param array $data Input value.
     * @return int Output value.
     */
    public function insert(array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, $columnSql, $placeholders);
        $this->connection->execute($sql, array_values($data));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update and return int.
     * @param array $data Input value.
     * @return int Output value.
     */
    public function update(array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = sprintf('%s = ?', $column);
            $params[] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s', $this->table, implode(', ', $setClauses));

        [$whereSql, $whereParams] = $this->compileWhereClause();
        if ($whereSql !== '') {
            $sql .= ' ' . $whereSql;
            $params = array_merge($params, $whereParams);
        }

        return $this->connection->execute($sql, $params);
    }

    /**
     * Delete and return int.
     * @return int Output value.
     */
    public function delete(): int
    {
        $sql = sprintf('DELETE FROM %s', $this->table);
        [$whereSql, $whereParams] = $this->compileWhereClause();

        if ($whereSql !== '') {
            $sql .= ' ' . $whereSql;
        }

        return $this->connection->execute($sql, $whereParams);
    }

    /**
     * Compile select and return array.
     * @return array Output value.
     */
    private function compileSelect(): array
    {
        $sql = sprintf('SELECT %s FROM %s', implode(', ', $this->columns), $this->table);
        [$whereSql, $params] = $this->compileWhereClause();

        if ($whereSql !== '') {
            $sql .= ' ' . $whereSql;
        }

        if ($this->orders !== []) {
            $orderSql = array_map(
                static fn (array $order): string => sprintf('%s %s', $order['column'], $order['direction']),
                $this->orders
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderSql);
        }

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return [$sql, $params];
    }

    /**
     * Compile where clause and return array.
     * @return array Output value.
     */
    private function compileWhereClause(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $clauses = [];
        $params = [];

        foreach ($this->wheres as $where) {
            if ($where['type'] === 'in') {
                $values = $where['values'] ?? [];
                if ($values === []) {
                    $clauses[] = '1 = 0';
                    continue;
                }

                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $clauses[] = sprintf('%s IN (%s)', $where['column'], $placeholders);
                $params = array_merge($params, $values);
                continue;
            }

            $clauses[] = sprintf('%s %s ?', $where['column'], $where['operator'] ?? '=');
            $params[] = $where['value'] ?? null;
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Hydrate models and return array.
     * @param array $rows Input value.
     * @return array Output value.
     */
    private function hydrateModels(array $rows): array
    {
        $modelClass = $this->modelClass;
        if ($modelClass === null) {
            return $rows;
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $modelClass::hydrate($row);
        }

        if ($this->relations !== []) {
            $modelClass::eagerLoad($models, $this->relations);
        }

        return $models;
    }
}

<?php

declare(strict_types=1);

namespace Maia\Orm;

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

    private function __construct(
        private string $table,
        private Connection $connection
    ) {
    }

    public static function table(string $table, Connection $connection): self
    {
        return new self($table, $connection);
    }

    public function select(string ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $this->columns = $columns;

        return $this;
    }

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
     * @param array<int, mixed> $values
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

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    public function with(string ...$relations): self
    {
        $this->relations = array_values(array_unique(array_merge($this->relations, $relations)));

        return $this;
    }

    public function forModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /** @return array<int, mixed> */
    public function get(): array
    {
        [$sql, $params] = $this->compileSelect();
        $rows = $this->connection->query($sql, $params);

        if ($this->modelClass === null) {
            return $rows;
        }

        return $this->hydrateModels($rows);
    }

    public function first(): mixed
    {
        $clone = clone $this;
        $clone->limit(1);
        $rows = $clone->get();

        return $rows[0] ?? null;
    }

    public function find(int|string $id): mixed
    {
        return $this->where('id', $id)->first();
    }

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

    /** @param array<string, mixed> $data */
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

    /** @param array<string, mixed> $data */
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
     * @return array{0: string, 1: array<int, mixed>}
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
     * @return array{0: string, 1: array<int, mixed>}
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
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, mixed>
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

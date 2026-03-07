<?php

declare(strict_types=1);

namespace Maia\Orm;

/**
 * Fluent SQL query builder supporting SELECT, INSERT, UPDATE, DELETE, joins, and eager-loading.
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

    /**
     * @var array<int, array{
     *     type: 'INNER'|'LEFT',
     *     table: string,
     *     first: string,
     *     operator: string,
     *     second: string
     * }>
     */
    private array $joins = [];

    /** @var array<int, string> */
    private array $groupBys = [];

    /**
     * @var array<int, array{
     *     type: 'basic'|'raw',
     *     column?: string,
     *     operator?: string,
     *     value?: mixed,
     *     sql?: string,
     *     params?: array<int, mixed>
     * }>
     */
    private array $havings = [];

    /** @var array<int, array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    /** @var array<int, string> */
    private array $relations = [];

    private ?string $modelClass = null;

    /**
     * Build a new query targeting the given table over the provided connection.
     * @param string $table The database table to query against.
     * @param Connection $connection The database connection to execute queries on.
     * @return void
     */
    private function __construct(
        private string $table,
        private Connection $connection
    ) {
    }

    /**
     * Create a new query builder for the specified table.
     * @param string $table The database table name.
     * @param Connection $connection The database connection to use.
     * @return self A fresh query builder instance.
     */
    public static function table(string $table, Connection $connection): self
    {
        return new self($table, $connection);
    }

    /**
     * Set the columns to retrieve in the SELECT clause.
     * @param string... $columns Column names or expressions to select.
     * @return self This builder for chaining.
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
     * Add a WHERE condition comparing a column to a value.
     * @param string $column The column name to filter on.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator (defaults to "=").
     * @return self This builder for chaining.
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
     * Add a WHERE IN condition; an empty values array produces a false condition.
     * @param string $column The column name to filter on.
     * @param array $values The list of values the column must match.
     * @return self This builder for chaining.
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
     * Add a JOIN clause to the query.
     * @param string $table The table to join.
     * @param string $first The left-hand column of the ON condition.
     * @param string $operator The comparison operator for the ON condition.
     * @param string $second The right-hand column of the ON condition.
     * @param string $type Join type: "INNER" or "LEFT" (defaults to "INNER").
     * @return self This builder for chaining.
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self {
        $normalizedType = strtoupper($type);
        $this->joins[] = [
            'type' => $normalizedType === 'LEFT' ? 'LEFT' : 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add a LEFT JOIN clause to the query.
     * @param string $table The table to join.
     * @param string $first The left-hand column of the ON condition.
     * @param string $operator The comparison operator for the ON condition.
     * @param string $second The right-hand column of the ON condition.
     * @return self This builder for chaining.
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add one or more columns to the GROUP BY clause.
     * @param string... $columns Column names to group by.
     * @return self This builder for chaining.
     */
    public function groupBy(string ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $this->groupBys = array_values(array_unique(array_merge($this->groupBys, $columns)));

        return $this;
    }

    /**
     * Add a HAVING condition comparing a column to a value.
     * @param string $column The column or aggregate expression to filter on.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator (defaults to "=").
     * @return self This builder for chaining.
     */
    public function having(string $column, mixed $value, string $operator = '='): self
    {
        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a raw HAVING expression with optional parameter bindings.
     * @param string $sql Raw SQL expression for the HAVING clause.
     * @param array<int, mixed> $params Positional parameter values to bind into the expression.
     * @return self This builder for chaining.
     */
    public function havingRaw(string $sql, array $params = []): self
    {
        $this->havings[] = [
            'type' => 'raw',
            'sql' => $sql,
            'params' => array_values($params),
        ];

        return $this;
    }

    /**
     * Add an ORDER BY clause for the given column.
     * @param string $column The column to sort by.
     * @param string $direction Sort direction: "asc" or "desc" (defaults to "asc").
     * @return self This builder for chaining.
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
     * Set the maximum number of rows to return.
     * @param int $limit Maximum row count (clamped to 0 minimum).
     * @return self This builder for chaining.
     */
    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);

        return $this;
    }

    /**
     * Set the number of rows to skip before returning results.
     * @param int $offset Number of rows to skip (clamped to 0 minimum).
     * @return self This builder for chaining.
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    /**
     * Register relation names to eager-load when hydrating models.
     * @param string... $relations Relation names defined on the model class.
     * @return self This builder for chaining.
     */
    public function with(string ...$relations): self
    {
        $this->relations = array_values(array_unique(array_merge($this->relations, $relations)));

        return $this;
    }

    /**
     * Bind this query to a Model subclass so results are hydrated as model instances.
     * @param string $modelClass Fully-qualified class name of the Model subclass.
     * @return self This builder for chaining.
     */
    public function forModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Execute the query and return all matching rows, hydrated as models if a model class is set.
     * @return array List of associative arrays or model instances.
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
     * Execute the query and return the first matching row, or null if none found.
     * @return mixed A single row (array or model instance), or null.
     */
    public function first(): mixed
    {
        $clone = clone $this;
        $clone->limit(1);
        $rows = $clone->get();

        return $rows[0] ?? null;
    }

    /**
     * Find a single row by its "id" column value.
     * @param int|string $id The primary key value to look up.
     * @return mixed The matching row or model instance, or null if not found.
     */
    public function find(int|string $id): mixed
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Return the number of rows matching the current query conditions.
     * @return int The row count.
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
     * Insert a row into the table and return the auto-generated ID.
     * @param array $data Column-value pairs to insert; an empty array is a no-op.
     * @return int The last insert ID, or 0 if data was empty.
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
     * Insert a row or update it on conflict using ON CONFLICT ... DO UPDATE.
     * @param array $data Column-value pairs to insert or update.
     * @param array<int, string> $conflictKeys Columns that form the unique constraint for conflict detection.
     * @return int Number of rows affected.
     */
    public function upsert(array $data, array $conflictKeys): int
    {
        if ($data === [] || $conflictKeys === []) {
            return 0;
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);
        $conflictSql = implode(', ', $conflictKeys);

        $updateColumns = array_values(array_diff($columns, $conflictKeys));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s)',
            $this->table,
            $columnSql,
            $placeholders,
            $conflictSql
        );

        if ($updateColumns === []) {
            $sql .= ' DO NOTHING';
        } else {
            $assignments = array_map(
                static fn (string $column): string => sprintf('%s = excluded.%s', $column, $column),
                $updateColumns
            );
            $sql .= ' DO UPDATE SET ' . implode(', ', $assignments);
        }

        return $this->connection->execute($sql, array_values($data));
    }

    /**
     * Update rows matching the current WHERE conditions with the given data.
     * @param array $data Column-value pairs to set; an empty array is a no-op.
     * @return int Number of rows affected.
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
     * Delete rows matching the current WHERE conditions.
     * @return int Number of rows deleted.
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
     * Compile the full SELECT SQL statement and its bound parameters from the builder state.
     * @return array{0: string, 1: array} A tuple of [sql, params].
     */
    private function compileSelect(): array
    {
        $sql = sprintf('SELECT %s FROM %s', implode(', ', $this->columns), $this->table);
        [$whereSql, $params] = $this->compileWhereClause();

        if ($this->joins !== []) {
            $joinSql = array_map(
                static fn (array $join): string => sprintf(
                    '%s JOIN %s ON %s %s %s',
                    $join['type'],
                    $join['table'],
                    $join['first'],
                    $join['operator'],
                    $join['second']
                ),
                $this->joins
            );
            $sql .= ' ' . implode(' ', $joinSql);
        }

        if ($whereSql !== '') {
            $sql .= ' ' . $whereSql;
        }

        if ($this->groupBys !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        [$havingSql, $havingParams] = $this->compileHavingClause();
        if ($havingSql !== '') {
            $sql .= ' ' . $havingSql;
            $params = array_merge($params, $havingParams);
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
     * Compile all HAVING conditions into a SQL fragment and its bound parameters.
     * @return array{0: string, 1: array} A tuple of [havingSql, params]; empty string if no conditions.
     */
    private function compileHavingClause(): array
    {
        return $this->compileConditionalClause(
            $this->havings,
            'HAVING',
            static function (array $having): array {
                if ($having['type'] === 'raw') {
                    return [$having['sql'] ?? '', $having['params'] ?? []];
                }

                return [
                    sprintf('%s %s ?', $having['column'], $having['operator'] ?? '='),
                    [$having['value'] ?? null],
                ];
            }
        );
    }

    /**
     * Compile all WHERE conditions into a SQL fragment and its bound parameters.
     * @return array{0: string, 1: array} A tuple of [whereSql, params]; empty string if no conditions.
     */
    private function compileWhereClause(): array
    {
        return $this->compileConditionalClause(
            $this->wheres,
            'WHERE',
            static function (array $where): array {
                if ($where['type'] === 'in') {
                    $values = $where['values'] ?? [];
                    if ($values === []) {
                        return ['1 = 0', []];
                    }

                    $placeholders = implode(', ', array_fill(0, count($values), '?'));

                    return [
                        sprintf('%s IN (%s)', $where['column'], $placeholders),
                        $values,
                    ];
                }

                return [
                    sprintf('%s %s ?', $where['column'], $where['operator'] ?? '='),
                    [$where['value'] ?? null],
                ];
            }
        );
    }

    /**
     * Compile a conditional clause list such as WHERE or HAVING into SQL and parameters.
     * @param array<int, array<string, mixed>> $conditions Clause definitions to compile.
     * @param string $keyword SQL clause keyword to prefix when conditions exist.
     * @param callable $compiler Callback(condition) => [sql, params].
     * @return array{0: string, 1: array<int, mixed>} A tuple of [clauseSql, params].
     */
    private function compileConditionalClause(array $conditions, string $keyword, callable $compiler): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $clauses = [];
        $params = [];

        foreach ($conditions as $condition) {
            [$sql, $conditionParams] = $compiler($condition);
            $clauses[] = $sql;
            $params = array_merge($params, $conditionParams);
        }

        return [$keyword . ' ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Convert raw database rows into model instances and eager-load any requested relations.
     * @param array $rows Raw associative-array rows from the database.
     * @return array List of hydrated model instances.
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

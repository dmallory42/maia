<?php

declare(strict_types=1);

namespace Maia\Orm\Schema;

/**
 * Table defines a framework component for this package.
 */
class Table
{
    /**
     * @var array<int, array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     default: mixed,
     *     unique: bool,
     *     primary: bool,
     *     autoincrement: bool
     * }>
     */
    private array $columns = [];

    /** @var array<int, string> */
    private array $uniqueColumns = [];

    private ?int $lastColumnIndex = null;

    /**
     * Id and return self.
     * @param string $name Input value.
     * @return self Output value.
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'INTEGER',
            'nullable' => false,
            'default' => null,
            'unique' => false,
            'primary' => true,
            'autoincrement' => true,
        ];

        $this->lastColumnIndex = array_key_last($this->columns);

        return $this;
    }

    /**
     * String and return self.
     * @param string $name Input value.
     * @param int $length Input value.
     * @return self Output value.
     */
    public function string(string $name, int $length = 255): self
    {
        return $this->addColumn($name, sprintf('VARCHAR(%d)', $length));
    }

    /**
     * Integer and return self.
     * @param string $name Input value.
     * @return self Output value.
     */
    public function integer(string $name): self
    {
        return $this->addColumn($name, 'INTEGER');
    }

    /**
     * Boolean and return self.
     * @param string $name Input value.
     * @return self Output value.
     */
    public function boolean(string $name): self
    {
        return $this->addColumn($name, 'INTEGER');
    }

    /**
     * Text and return self.
     * @param string $name Input value.
     * @return self Output value.
     */
    public function text(string $name): self
    {
        return $this->addColumn($name, 'TEXT');
    }

    /**
     * Timestamps and return self.
     * @return self Output value.
     */
    public function timestamps(): self
    {
        $this->addColumn('created_at', 'DATETIME');
        $this->addColumn('updated_at', 'DATETIME');

        return $this;
    }

    /**
     * Unique and return self.
     * @param string|null $column Input value.
     * @return self Output value.
     */
    public function unique(?string $column = null): self
    {
        if ($column !== null) {
            $this->uniqueColumns[] = $column;

            return $this;
        }

        if ($this->lastColumnIndex !== null) {
            $this->columns[$this->lastColumnIndex]['unique'] = true;
        }

        return $this;
    }

    public function default(mixed $value): self
    {
        if ($this->lastColumnIndex !== null) {
            $this->columns[$this->lastColumnIndex]['default'] = $value;
        }

        return $this;
    }

    /**
     * Nullable and return self.
     * @return self Output value.
     */
    public function nullable(): self
    {
        if ($this->lastColumnIndex !== null) {
            $this->columns[$this->lastColumnIndex]['nullable'] = true;
        }

        return $this;
    }

    /**
     * To create sql and return string.
     * @param string $table Input value.
     * @return string Output value.
     */
    public function toCreateSql(string $table): string
    {
        $parts = [];

        foreach ($this->columns as $column) {
            $sql = sprintf('`%s` %s', $column['name'], $column['type']);

            if ($column['primary']) {
                $sql .= ' PRIMARY KEY';
            }

            if ($column['autoincrement']) {
                $sql .= ' AUTOINCREMENT';
            }

            if (!$column['primary'] && !$column['nullable']) {
                $sql .= ' NOT NULL';
            }

            if ($column['default'] !== null) {
                $sql .= ' DEFAULT ' . $this->formatDefault($column['default']);
            }

            if ($column['unique']) {
                $sql .= ' UNIQUE';
            }

            $parts[] = $sql;
        }

        foreach ($this->uniqueColumns as $uniqueColumn) {
            $parts[] = sprintf('UNIQUE (`%s`)', $uniqueColumn);
        }

        return sprintf('CREATE TABLE IF NOT EXISTS `%s` (%s)', $table, implode(', ', $parts));
    }

    /**
     * Add column and return self.
     * @param string $name Input value.
     * @param string $type Input value.
     * @return self Output value.
     */
    private function addColumn(string $name, string $type): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'default' => null,
            'unique' => false,
            'primary' => false,
            'autoincrement' => false,
        ];

        $this->lastColumnIndex = array_key_last($this->columns);

        return $this;
    }

    /**
     * Format default and return string.
     * @param mixed $value Input value.
     * @return string Output value.
     */
    private function formatDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}

<?php

declare(strict_types=1);

namespace Maia\Orm\Schema;

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

    public function string(string $name, int $length = 255): self
    {
        return $this->addColumn($name, sprintf('VARCHAR(%d)', $length));
    }

    public function integer(string $name): self
    {
        return $this->addColumn($name, 'INTEGER');
    }

    public function boolean(string $name): self
    {
        return $this->addColumn($name, 'INTEGER');
    }

    public function text(string $name): self
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function timestamps(): self
    {
        $this->addColumn('created_at', 'DATETIME');
        $this->addColumn('updated_at', 'DATETIME');

        return $this;
    }

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

    public function nullable(): self
    {
        if ($this->lastColumnIndex !== null) {
            $this->columns[$this->lastColumnIndex]['nullable'] = true;
        }

        return $this;
    }

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

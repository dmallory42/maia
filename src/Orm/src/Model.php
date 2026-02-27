<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Attributes\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

abstract class Model
{
    protected static ?Connection $connection = null;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $relationCache = [];

    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    public static function query(): QueryBuilder
    {
        return QueryBuilder::table(static::tableName(), static::connection())
            ->forModel(static::class);
    }

    public static function find(int|string $id): ?static
    {
        $result = static::query()->where(static::primaryKey(), $id)->first();

        return $result instanceof static ? $result : null;
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): static
    {
        $id = static::query()->insert($data);
        $model = static::find($id);

        if ($model instanceof static) {
            return $model;
        }

        $data[static::primaryKey()] = $id;

        return static::hydrate($data);
    }

    public function save(): bool
    {
        $primaryKey = static::primaryKey();
        $primaryValue = $this->$primaryKey ?? $this->attributes[$primaryKey] ?? null;

        if ($primaryValue === null) {
            throw new RuntimeException(sprintf('Cannot save %s without primary key.', static::class));
        }

        $data = $this->extractPersistableData();
        unset($data[$primaryKey]);

        if ($data === []) {
            return false;
        }

        $affected = static::query()->where($primaryKey, $primaryValue)->update($data);

        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $affected > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $reflection = new ReflectionClass($model);

        foreach ($row as $key => $value) {
            $model->attributes[$key] = $value;

            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $property->setValue($model, static::castValueForProperty($property, $value));
            }
        }

        return $model;
    }

    /**
     * @param array<int, self> $models
     * @param array<int, string> $relations
     */
    public static function eagerLoad(array $models, array $relations): void
    {
        foreach ($models as $model) {
            foreach ($relations as $relation) {
                $model->__get($relation);
            }
        }
    }

    public static function tableName(): string
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Table::class);

        if ($attributes !== []) {
            /** @var Table $table */
            $table = $attributes[0]->newInstance();

            return $table->name;
        }

        return strtolower($reflection->getShortName()) . 's';
    }

    public static function primaryKey(): string
    {
        return 'id';
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->relationCache)) {
            return $this->relationCache[$name];
        }

        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    protected static function connection(): Connection
    {
        if (static::$connection === null) {
            throw new RuntimeException(sprintf('No ORM connection configured for model %s.', static::class));
        }

        return static::$connection;
    }

    /** @return array<string, mixed> */
    private function extractPersistableData(): array
    {
        $data = $this->attributes;
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($this);

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $data[$name] = $value;
        }

        return $data;
    }

    private static function castValueForProperty(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin() || $value === null) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }
}

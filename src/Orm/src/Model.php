<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Attributes\BelongsTo;
use Maia\Orm\Attributes\HasMany;
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
        $primaryValue = $this->readValue($primaryKey);

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

            if (!$reflection->hasProperty($key)) {
                continue;
            }

            $property = $reflection->getProperty($key);
            $property->setValue($model, static::castValueForProperty($property, $value));
        }

        return $model;
    }

    /**
     * @param array<int, self> $models
     * @param array<int, string> $relations
     */
    public static function eagerLoad(array $models, array $relations): void
    {
        foreach ($relations as $relation) {
            static::eagerLoadRelation($models, $relation);
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

        $relation = static::relationDefinition($name);
        if ($relation !== null) {
            return $this->loadRelation($name, $relation);
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

            if (static::propertyHasRelationshipAttribute($property)) {
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

    private function readValue(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        $reflection = new ReflectionClass($this);
        if (!$reflection->hasProperty($name)) {
            return null;
        }

        $property = $reflection->getProperty($name);
        if (!$property->isInitialized($this)) {
            return null;
        }

        return $property->getValue($this);
    }

    /**
     * @param array{type: 'hasMany'|'belongsTo', relatedClass: class-string<Model>, foreignKey: string} $relation
     */
    private function loadRelation(string $name, array $relation): mixed
    {
        if ($relation['type'] === 'hasMany') {
            $primaryValue = $this->readValue(static::primaryKey());
            $value = $primaryValue === null
                ? []
                : $relation['relatedClass']::query()->where($relation['foreignKey'], $primaryValue)->get();
            $this->setRelation($name, $value);

            return $value;
        }

        $foreignValue = $this->readValue($relation['foreignKey']);
        $value = $foreignValue === null ? null : $relation['relatedClass']::find($foreignValue);
        $this->setRelation($name, $value);

        return $value;
    }

    /**
     * @param array<int, self> $models
     */
    private static function eagerLoadRelation(array $models, string $relationName): void
    {
        if ($models === []) {
            return;
        }

        $relation = static::relationDefinition($relationName);
        if ($relation === null) {
            return;
        }

        if ($relation['type'] === 'hasMany') {
            $primaryKey = static::primaryKey();
            $ids = [];

            foreach ($models as $model) {
                $value = $model->readValue($primaryKey);
                if ($value !== null) {
                    $ids[] = $value;
                }
            }

            $ids = array_values(array_unique($ids));
            $grouped = [];

            if ($ids !== []) {
                $relatedRows = $relation['relatedClass']::query()
                    ->whereIn($relation['foreignKey'], $ids)
                    ->get();

                foreach ($relatedRows as $related) {
                    $foreignValue = $related->{$relation['foreignKey']} ?? null;
                    if ($foreignValue === null) {
                        continue;
                    }

                    $grouped[(string) $foreignValue][] = $related;
                }
            }

            foreach ($models as $model) {
                $id = $model->readValue($primaryKey);
                $model->setRelation($relationName, $id === null ? [] : ($grouped[(string) $id] ?? []));
            }

            return;
        }

        $ids = [];

        foreach ($models as $model) {
            $value = $model->readValue($relation['foreignKey']);
            if ($value !== null) {
                $ids[] = $value;
            }
        }

        $ids = array_values(array_unique($ids));
        $indexed = [];

        if ($ids !== []) {
            $relatedPrimaryKey = $relation['relatedClass']::primaryKey();
            $relatedRows = $relation['relatedClass']::query()
                ->whereIn($relatedPrimaryKey, $ids)
                ->get();

            foreach ($relatedRows as $related) {
                $primaryValue = $related->{$relatedPrimaryKey} ?? null;
                if ($primaryValue === null) {
                    continue;
                }

                $indexed[(string) $primaryValue] = $related;
            }
        }

        foreach ($models as $model) {
            $foreignValue = $model->readValue($relation['foreignKey']);
            $model->setRelation($relationName, $foreignValue === null ? null : ($indexed[(string) $foreignValue] ?? null));
        }
    }

    /**
     * @return array{type: 'hasMany'|'belongsTo', relatedClass: class-string<Model>, foreignKey: string}|null
     */
    private static function relationDefinition(string $name): ?array
    {
        $reflection = new ReflectionClass(static::class);
        if (!$reflection->hasProperty($name)) {
            return null;
        }

        $property = $reflection->getProperty($name);
        $hasManyAttributes = $property->getAttributes(HasMany::class);
        if ($hasManyAttributes !== []) {
            /** @var HasMany $hasMany */
            $hasMany = $hasManyAttributes[0]->newInstance();

            return [
                'type' => 'hasMany',
                'relatedClass' => $hasMany->relatedClass,
                'foreignKey' => $hasMany->foreignKey ?? static::inferForeignKey(static::class),
            ];
        }

        $belongsToAttributes = $property->getAttributes(BelongsTo::class);
        if ($belongsToAttributes !== []) {
            /** @var BelongsTo $belongsTo */
            $belongsTo = $belongsToAttributes[0]->newInstance();

            return [
                'type' => 'belongsTo',
                'relatedClass' => $belongsTo->relatedClass,
                'foreignKey' => $belongsTo->foreignKey ?? static::inferForeignKey($belongsTo->relatedClass),
            ];
        }

        return null;
    }

    private function setRelation(string $name, mixed $value): void
    {
        $this->relationCache[$name] = $value;

        $reflection = new ReflectionClass($this);
        if (!$reflection->hasProperty($name)) {
            return;
        }

        $property = $reflection->getProperty($name);
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            if ($value === null && !$type->allowsNull()) {
                return;
            }

            if ($type->isBuiltin()) {
                $expected = $type->getName();
                if ($expected === 'array' && !is_array($value)) {
                    return;
                }
                if ($expected === 'string' && !is_string($value)) {
                    return;
                }
                if ($expected === 'int' && !is_int($value)) {
                    return;
                }
                if ($expected === 'float' && !is_float($value) && !is_int($value)) {
                    return;
                }
                if ($expected === 'bool' && !is_bool($value)) {
                    return;
                }
            } elseif ($value !== null && !is_a($value, $type->getName())) {
                return;
            }
        }

        $property->setValue($this, $value);
    }

    private static function propertyHasRelationshipAttribute(ReflectionProperty $property): bool
    {
        return $property->getAttributes(HasMany::class) !== []
            || $property->getAttributes(BelongsTo::class) !== [];
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

    private static function inferForeignKey(string $class): string
    {
        $reflection = new ReflectionClass($class);
        $shortName = $reflection->getShortName();
        $snake = (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($snake) . '_id';
    }
}

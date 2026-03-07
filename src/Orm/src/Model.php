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

/**
 * Active-record base class providing CRUD operations, attribute access, and relationship loading.
 */
abstract class Model
{
    protected static ?Connection $connection = null;

    /** @var array<class-string, ReflectionClass<object>> */
    private static array $reflectionCache = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $relationCache = [];

    /**
     * Set the database connection used by all instances of this model.
     * @param Connection $connection The database connection to use for queries.
     * @return void
     */
    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Start a new query builder scoped to this model's table.
     * @return QueryBuilder A query builder bound to this model class.
     */
    public static function query(): QueryBuilder
    {
        return QueryBuilder::table(static::tableName(), static::connection())
            ->forModel(static::class);
    }

    /**
     * Find a model by its primary key, or return null if not found.
     * @param int|string $id The primary key value to look up.
     * @return static|null The matching model instance or null.
     */
    public static function find(int|string $id): ?static
    {
        $result = static::query()->where(static::primaryKey(), $id)->first();

        return $result instanceof static ? $result : null;
    }

    /**
     * Insert a new row and return the hydrated model instance.
     * @param array $data Column-value pairs to insert.
     * @return static The newly created model.
     */
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

    /**
     * Persist the model's current attributes to the database via UPDATE.
     * @return bool True if at least one row was affected, false otherwise.
     */
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
     * Create a model instance from a database row without inserting it.
     * @param array $row Associative array of column names to values.
     * @return static The hydrated model with attributes and typed properties set.
     */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $reflection = static::reflectionFor($model::class);

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
     * Batch-load the specified relations for a collection of models to avoid N+1 queries.
     * @param array $models List of model instances to load relations onto.
     * @param array $relations Relation names to eager-load.
     * @return void
     */
    public static function eagerLoad(array $models, array $relations): void
    {
        foreach ($relations as $relation) {
            static::eagerLoadRelation($models, $relation);
        }
    }

    /**
     * Return the database table name, using the #[Table] attribute or a lowercased plural convention.
     * @return string The table name for this model.
     */
    public static function tableName(): string
    {
        $reflection = static::reflectionFor(static::class);
        $attributes = $reflection->getAttributes(Table::class);

        if ($attributes !== []) {
            /** @var Table $table */
            $table = $attributes[0]->newInstance();

            return $table->name;
        }

        return strtolower($reflection->getShortName()) . 's';
    }

    /**
     * Return the primary key column name (defaults to "id").
     * @return string The primary key column name.
     */
    public static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Access an attribute or lazy-load a relation by property name.
     * @param string $name The attribute or relation name.
     * @return mixed The attribute value, loaded relation, or null if not found.
     */
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

    /**
     * Set an attribute value dynamically.
     * @param string $name The attribute name to assign.
     * @param mixed $value The value to store.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Return the configured database connection for the model class.
     * @return Connection The active database connection.
     */
    protected static function connection(): Connection
    {
        if (static::$connection === null) {
            throw new RuntimeException(sprintf('No ORM connection configured for model %s.', static::class));
        }

        return static::$connection;
    }

    /**
     * Collect scalar attributes and initialized public properties for persistence.
     * @return array Column-value pairs safe to include in INSERT or UPDATE statements.
     */
    private function extractPersistableData(): array
    {
        $data = $this->attributes;
        $reflection = static::reflectionFor($this::class);

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

    /**
     * Read an attribute or initialized typed property value.
     * @param string $name The attribute or property name to read.
     * @return mixed The current value, or null if absent/uninitialized.
     */
    private function readValue(string $name): mixed
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        $reflection = static::reflectionFor($this::class);
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
     * Load a relation on demand and cache the resolved value.
     * @param string $name The relation property name.
     * @param array $relation Relation metadata describing the type, related class, and key columns.
     * @return mixed The loaded relation value (model, collection, or null).
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
     * Eager-load one named relation across a set of models.
     * @param array $models The models that should receive the relation data.
     * @param string $relationName The relation name to load.
     * @return void
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
            $model->setRelation(
                $relationName,
                $foreignValue === null ? null : ($indexed[(string) $foreignValue] ?? null)
            );
        }
    }

    /**
     * Resolve relationship metadata from HasMany/BelongsTo attributes on a property.
     * @param string $name The property name to inspect.
     * @return array|null Relation metadata, or null if the property is not a declared relation.
     */
    private static function relationDefinition(string $name): ?array
    {
        $reflection = static::reflectionFor(static::class);
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

    /**
     * Cache a loaded relation and mirror it onto the typed property when compatible.
     * @param string $name The relation property name.
     * @param mixed $value The loaded relation value to cache.
     * @return void
     */
    private function setRelation(string $name, mixed $value): void
    {
        $this->relationCache[$name] = $value;

        $reflection = static::reflectionFor($this::class);
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

    /**
     * Check whether a property declares a relationship attribute.
     * @param ReflectionProperty $property The property to inspect.
     * @return bool True if the property is marked with HasMany or BelongsTo.
     */
    private static function propertyHasRelationshipAttribute(ReflectionProperty $property): bool
    {
        return $property->getAttributes(HasMany::class) !== []
            || $property->getAttributes(BelongsTo::class) !== [];
    }

    /**
     * Cast a raw database value to match a property's builtin declared type.
     * @param ReflectionProperty $property The typed property receiving the value.
     * @param mixed $value The raw value from the database row.
     * @return mixed The cast value, or the original value when no builtin cast applies.
     */
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

    /**
     * Infer a snake_cased foreign-key column name from a model class name.
     * @param string $class Fully qualified model class name.
     * @return string Foreign key column name such as "user_id".
     */
    private static function inferForeignKey(string $class): string
    {
        $reflection = static::reflectionFor($class);
        $shortName = $reflection->getShortName();
        $snake = (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($snake) . '_id';
    }

    /**
     * Return a cached ReflectionClass instance for the given model class.
     * @param class-string $class Fully qualified model class name.
     * @return ReflectionClass<object> Cached reflection metadata for the class.
     */
    private static function reflectionFor(string $class): ReflectionClass
    {
        /** @var ReflectionClass<object> */
        return self::$reflectionCache[$class] ??= new ReflectionClass($class);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function factory(string $class, Closure $factory): void
    {
        $this->factories[$class] = $factory;
    }

    public function singleton(string $class, ?Closure $factory = null): void
    {
        $this->singletons[$class] = true;

        if ($factory !== null) {
            $this->factories[$class] = $factory;
        }
    }

    public function instance(string $class, object $instance): void
    {
        $this->instances[$class] = $instance;
    }

    public function resolve(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (isset($this->factories[$class])) {
            $instance = ($this->factories[$class])($this);
        } else {
            $instance = $this->autoWire($class);
        }

        if (isset($this->singletons[$class])) {
            $this->instances[$class] = $instance;
        }

        return $instance;
    }

    private function autoWire(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot auto-wire [{$class}]: not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $params = array_map(
            fn (ReflectionParameter $param): mixed => $this->resolveParameter($param, $class),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($params);
    }

    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->resolve($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot resolve parameter [{$param->getName()}] in [{$forClass}]: no type hint or default value."
        );
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Container defines a framework component for this package.
 */
class Container
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Factory and return void.
     * @param string $class Input value.
     * @param Closure $factory Input value.
     * @return void Output value.
     */
    public function factory(string $class, Closure $factory): void
    {
        $this->factories[$class] = $factory;
    }

    /**
     * Singleton and return void.
     * @param string $class Input value.
     * @param Closure|null $factory Input value.
     * @return void Output value.
     */
    public function singleton(string $class, ?Closure $factory = null): void
    {
        $this->singletons[$class] = true;

        if ($factory !== null) {
            $this->factories[$class] = $factory;
        }
    }

    /**
     * Instance and return void.
     * @param string $class Input value.
     * @param object $instance Input value.
     * @return void Output value.
     */
    public function instance(string $class, object $instance): void
    {
        $this->instances[$class] = $instance;
    }

    /**
     * Resolve and return object.
     * @param string $class Input value.
     * @return object Output value.
     */
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

    /**
     * Auto wire and return object.
     * @param string $class Input value.
     * @return object Output value.
     */
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

    /**
     * Resolve parameter and return mixed.
     * @param ReflectionParameter $param Input value.
     * @param string $forClass Input value.
     * @return mixed Output value.
     */
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

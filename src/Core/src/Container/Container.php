<?php

declare(strict_types=1);

namespace Maia\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Dependency injection container with auto-wiring, factory registration, and singleton support.
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
     * Register a factory closure that produces a new instance each time the class is resolved.
     * @param string $class Fully qualified class name or interface to bind.
     * @param Closure $factory Callable that receives the container and returns the instance.
     * @return void
     */
    public function factory(string $class, Closure $factory): void
    {
        $this->factories[$class] = $factory;
    }

    /**
     * Mark a class as a singleton so only one instance is created and reused.
     * @param string $class Fully qualified class name or interface to bind as a singleton.
     * @param Closure|null $factory Optional factory closure; if null, the class is auto-wired.
     * @return void
     */
    public function singleton(string $class, ?Closure $factory = null): void
    {
        $this->singletons[$class] = true;

        if ($factory !== null) {
            $this->factories[$class] = $factory;
        }
    }

    /**
     * Bind an existing object instance to a class name in the container.
     * @param string $class Fully qualified class name or interface the instance satisfies.
     * @param object $instance The pre-built object to return on every resolve call.
     * @return void
     */
    public function instance(string $class, object $instance): void
    {
        $this->instances[$class] = $instance;
    }

    /**
     * Resolve a class from the container using registered bindings or auto-wiring.
     * @param string $class Fully qualified class name or interface to resolve.
     * @return object The resolved instance.
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
     * Instantiate a class by recursively resolving its constructor dependencies.
     * @param string $class Fully qualified class name to instantiate.
     * @return object The newly created instance with all dependencies injected.
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
     * Resolve a single constructor parameter by type hint, default value, or throw on failure.
     * @param ReflectionParameter $param The constructor parameter to resolve.
     * @param string $forClass The class being constructed, used in error messages.
     * @return mixed The resolved value for the parameter.
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

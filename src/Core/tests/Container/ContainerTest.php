<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Container;

use Maia\Core\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SimpleClass
{
    public function value(): string
    {
        return 'simple';
    }
}

class DependentClass
{
    public function __construct(public SimpleClass $dep)
    {
    }
}

class DeepDependencyClass
{
    public function __construct(public DependentClass $dep)
    {
    }
}

class UnresolvableClass
{
    public function __construct(public string $name)
    {
    }
}

class ContainerTest extends TestCase
{
    public function testResolvesSimpleClass(): void
    {
        $container = new Container();
        $instance = $container->resolve(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
        $this->assertEquals('simple', $instance->value());
    }

    public function testAutoWiresConstructorDependencies(): void
    {
        $container = new Container();
        $instance = $container->resolve(DependentClass::class);

        $this->assertInstanceOf(DependentClass::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep);
    }

    public function testResolvesDeepDependencyChain(): void
    {
        $container = new Container();
        $instance = $container->resolve(DeepDependencyClass::class);

        $this->assertInstanceOf(DeepDependencyClass::class, $instance);
        $this->assertInstanceOf(DependentClass::class, $instance->dep);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep->dep);
    }

    public function testFactoryBinding(): void
    {
        $container = new Container();
        $container->factory(SimpleClass::class, fn () => new SimpleClass());

        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);

        $this->assertNotSame($a, $b);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class);

        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);

        $this->assertSame($a, $b);
    }

    public function testSingletonWithFactory(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class, fn () => new SimpleClass());

        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);

        $this->assertSame($a, $b);
    }

    public function testThrowsOnUnresolvableParameter(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $container->resolve(UnresolvableClass::class);
    }
}

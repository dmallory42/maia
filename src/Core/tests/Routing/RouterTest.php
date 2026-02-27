<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Routing;

use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
use Maia\Core\Routing\Router;
use PHPUnit\Framework\TestCase;

#[Controller('/users')]
class TestUserController
{
    #[Route('/', method: 'GET')]
    public function list(): string
    {
        return 'list';
    }

    #[Route('/{id}', method: 'GET')]
    public function show(int $id): string
    {
        return "show:{$id}";
    }

    #[Route('/', method: 'POST')]
    public function create(): string
    {
        return 'create';
    }
}

#[Controller('/posts')]
class TestPostController
{
    #[Route('/{slug}', method: 'GET')]
    public function show(string $slug): string
    {
        return "post:{$slug}";
    }
}

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->registerController(TestUserController::class);
        $this->router->registerController(TestPostController::class);
    }

    public function testMatchesSimpleRoute(): void
    {
        $match = $this->router->match('GET', '/users');

        $this->assertNotNull($match);
        $this->assertEquals(TestUserController::class, $match->controller);
        $this->assertEquals('list', $match->method);
    }

    public function testMatchesParameterizedRoute(): void
    {
        $match = $this->router->match('GET', '/users/42');

        $this->assertNotNull($match);
        $this->assertEquals('show', $match->method);
        $this->assertEquals(['id' => '42'], $match->params);
    }

    public function testMatchesCorrectHttpMethod(): void
    {
        $match = $this->router->match('POST', '/users');

        $this->assertNotNull($match);
        $this->assertEquals('create', $match->method);
    }

    public function testReturnsNullForNoMatch(): void
    {
        $match = $this->router->match('GET', '/nonexistent');

        $this->assertNull($match);
    }

    public function testReturnsNullForWrongMethod(): void
    {
        $match = $this->router->match('DELETE', '/users');

        $this->assertNull($match);
    }

    public function testMatchesStringParams(): void
    {
        $match = $this->router->match('GET', '/posts/hello-world');

        $this->assertNotNull($match);
        $this->assertEquals(['slug' => 'hello-world'], $match->params);
    }

    public function testListsAllRoutes(): void
    {
        $routes = $this->router->routes();

        $this->assertCount(4, $routes);
    }
}

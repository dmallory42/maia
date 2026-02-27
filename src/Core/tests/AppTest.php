<?php

declare(strict_types=1);

namespace Maia\Core\Tests;

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
use PHPUnit\Framework\TestCase;

#[Controller('/test')]
class TestController
{
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        return Response::json(['message' => 'hello']);
    }

    #[Route('/error', method: 'GET')]
    public function error(): Response
    {
        throw new \Maia\Core\Exceptions\NotFoundException('Not here');
    }
}

class AppTest extends TestCase
{
    public function testHandlesMatchedRequest(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('hello', $response->body());
    }

    public function testReturns404ForUnmatchedRoute(): void
    {
        $app = App::create();
        $request = new Request('GET', '/nothing', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testHandlesExceptionsGracefully(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test/error', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }
}

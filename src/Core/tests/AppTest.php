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

#[Controller('/typed')]
class TypedRouteController
{
    #[Route('/int/{id}', method: 'GET')]
    public function int(int $id): Response
    {
        return Response::json(['id' => $id]);
    }

    #[Route('/float/{value}', method: 'GET')]
    public function float(float $value): Response
    {
        return Response::json(['value' => $value]);
    }

    #[Route('/bool/{flag}', method: 'GET')]
    public function bool(bool $flag): Response
    {
        return Response::json(['flag' => $flag]);
    }

    #[Route('/string/{slug}', method: 'GET')]
    public function string(string $slug): Response
    {
        return Response::json(['slug' => $slug]);
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

    public function testValidBuiltinRouteParametersResolveCorrectly(): void
    {
        $app = App::create();
        $app->registerController(TypedRouteController::class);

        $intResponse = $app->handle(new Request('GET', '/typed/int/42', [], [], null, []));
        $floatResponse = $app->handle(new Request('GET', '/typed/float/4.2', [], [], null, []));
        $boolResponse = $app->handle(new Request('GET', '/typed/bool/true', [], [], null, []));
        $stringResponse = $app->handle(new Request('GET', '/typed/string/hello-world', [], [], null, []));

        $this->assertSame('{"id":42}', $intResponse->body());
        $this->assertSame('{"value":4.2}', $floatResponse->body());
        $this->assertSame('{"flag":true}', $boolResponse->body());
        $this->assertSame('{"slug":"hello-world"}', $stringResponse->body());
    }

    public function testInvalidBuiltinRouteParametersReturn404(): void
    {
        $app = App::create();
        $app->registerController(TypedRouteController::class);

        $intResponse = $app->handle(new Request('GET', '/typed/int/not-a-number', [], [], null, []));
        $floatResponse = $app->handle(new Request('GET', '/typed/float/not-a-float', [], [], null, []));
        $boolResponse = $app->handle(new Request('GET', '/typed/bool/not-a-bool', [], [], null, []));

        $this->assertSame(404, $intResponse->status());
        $this->assertSame(404, $floatResponse->status());
        $this->assertSame(404, $boolResponse->status());
    }
}

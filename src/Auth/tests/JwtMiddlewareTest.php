<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\Auth;
use Maia\Auth\JwtMiddleware;
use Maia\Auth\JwtService;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class JwtMiddlewareTest extends TestCase
{
    private JwtService $service;
    private JwtMiddleware $middleware;

    protected function setUp(): void
    {
        $this->service = new JwtService('test-secret-key-32-chars-minimum!!');
        $this->middleware = new JwtMiddleware($this->service);
    }

    public function testRequestWithValidJwtPassesAndPopulatesUser(): void
    {
        $token = $this->service->encode(['sub' => 123, 'name' => 'Mal']);
        $request = new Request('GET', '/', [], ['Authorization' => 'Bearer ' . $token], null, []);

        $captured = null;
        $response = $this->middleware->handle($request, function (Request $req) use (&$captured): Response {
            $captured = $req;

            return Response::json(['ok' => true]);
        });

        $this->assertSame(200, $response->status());
        $this->assertInstanceOf(Request::class, $captured);
        $this->assertSame(123, (int) $captured->user()->sub);
    }

    public function testRequestWithExpiredJwtReturns401(): void
    {
        $token = $this->service->encode(['sub' => 123, 'exp' => time() - 60]);
        $request = new Request('GET', '/', [], ['Authorization' => 'Bearer ' . $token], null, []);

        $response = $this->middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(401, $response->status());
    }

    public function testRequestWithNoAuthorizationHeaderReturns401(): void
    {
        $request = new Request('GET', '/', [], [], null, []);

        $response = $this->middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(401, $response->status());
    }

    public function testRequestWithMalformedTokenReturns401(): void
    {
        $request = new Request('GET', '/', [], ['Authorization' => 'Bearer invalid.token'], null, []);

        $response = $this->middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(401, $response->status());
    }

    public function testAuthFactoryReturnsConfiguredMiddleware(): void
    {
        $middleware = Auth::jwt('test-secret-key-32-chars-minimum!!');

        $this->assertInstanceOf(JwtMiddleware::class, $middleware);
    }
}

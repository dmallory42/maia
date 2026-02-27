<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;
use Maia\Core\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;

class AddHeaderMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $response->withHeader('X-Added', 'true');
    }
}

class ShortCircuitMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return Response::error('Blocked', 403);
    }
}

class ModifyRequestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request = $request->withAttribute('modified', true);

        return $next($request);
    }
}

class PipelineTest extends TestCase
{
    public function testExecutesHandlerWithNoMiddleware(): void
    {
        $pipeline = new Pipeline([]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn (Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals(200, $response->status());
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $pipeline = new Pipeline([new AddHeaderMiddleware()]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn (Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals('true', $response->header('X-Added'));
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $pipeline = new Pipeline([
            new ShortCircuitMiddleware(),
            new AddHeaderMiddleware(),
        ]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn (Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals(403, $response->status());
        $this->assertNull($response->header('X-Added'));
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $capturedRequest = null;
        $pipeline = new Pipeline([new ModifyRequestMiddleware()]);
        $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            function (Request $req) use (&$capturedRequest) {
                $capturedRequest = $req;

                return Response::json(['ok' => true]);
            }
        );

        $this->assertTrue($capturedRequest->attribute('modified'));
    }

    public function testMiddlewareExecutesInOrder(): void
    {
        $order = [];

        $m1 = new class ($order) implements Middleware {
            /** @var array<int, string> */
            private array $order;

            /** @param array<int, string> $order */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function handle(Request $request, Closure $next): Response
            {
                $this->order[] = 'before:1';
                $response = $next($request);
                $this->order[] = 'after:1';

                return $response;
            }
        };

        $m2 = new class ($order) implements Middleware {
            /** @var array<int, string> */
            private array $order;

            /** @param array<int, string> $order */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function handle(Request $request, Closure $next): Response
            {
                $this->order[] = 'before:2';
                $response = $next($request);
                $this->order[] = 'after:2';

                return $response;
            }
        };

        $pipeline = new Pipeline([$m1, $m2]);
        $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn (Request $req) => Response::json([])
        );

        $this->assertEquals(['before:1', 'before:2', 'after:2', 'after:1'], $order);
    }
}

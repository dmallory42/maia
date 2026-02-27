<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\RateLimit;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        RateLimit::reset();
    }

    public function testPerMinuteFactoryCreatesMiddleware(): void
    {
        $middleware = RateLimit::perMinute(5);

        $this->assertInstanceOf(RateLimit::class, $middleware);
    }

    public function testBlocksAfterLimitExceeded(): void
    {
        $middleware = RateLimit::perMinute(2);
        $request = new Request('GET', '/resource', [], ['X-Forwarded-For' => '1.2.3.4'], null, []);

        $first = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $second = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $third = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $first->status());
        $this->assertSame(200, $second->status());
        $this->assertSame(429, $third->status());
    }

    public function testDifferentClientsAreTrackedSeparately(): void
    {
        $middleware = RateLimit::perMinute(1);

        $clientA = new Request('GET', '/resource', [], ['X-Forwarded-For' => '1.2.3.4'], null, []);
        $clientB = new Request('GET', '/resource', [], ['X-Forwarded-For' => '5.6.7.8'], null, []);

        $a1 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $a2 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $b1 = $middleware->handle($clientB, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $a1->status());
        $this->assertSame(429, $a2->status());
        $this->assertSame(200, $b1->status());
    }
}

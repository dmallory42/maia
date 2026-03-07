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
        $request = new Request('GET', '/resource', [], [], null, [], ['remote_addr' => '1.2.3.4']);

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

        $clientA = new Request('GET', '/resource', [], [], null, [], ['remote_addr' => '1.2.3.4']);
        $clientB = new Request('GET', '/resource', [], [], null, [], ['remote_addr' => '5.6.7.8']);

        $a1 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $a2 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $b1 = $middleware->handle($clientB, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $a1->status());
        $this->assertSame(429, $a2->status());
        $this->assertSame(200, $b1->status());
    }

    public function testTrustedProxyUsesForwardedClientAddress(): void
    {
        $middleware = RateLimit::perMinute(1, ['10.0.0.10']);

        $clientA = new Request(
            'GET',
            '/resource',
            [],
            ['X-Forwarded-For' => '1.2.3.4'],
            null,
            [],
            ['remote_addr' => '10.0.0.10']
        );
        $clientB = new Request(
            'GET',
            '/resource',
            [],
            ['X-Forwarded-For' => '5.6.7.8'],
            null,
            [],
            ['remote_addr' => '10.0.0.10']
        );

        $a1 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $a2 = $middleware->handle($clientA, fn (Request $req): Response => Response::json(['ok' => true]));
        $b1 = $middleware->handle($clientB, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $a1->status());
        $this->assertSame(429, $a2->status());
        $this->assertSame(200, $b1->status());
    }

    public function testIgnoresSpoofedForwardedForHeaderFromUntrustedPeer(): void
    {
        $middleware = RateLimit::perMinute(1);

        $first = new Request(
            'GET',
            '/resource',
            [],
            ['X-Forwarded-For' => '1.2.3.4'],
            null,
            [],
            ['remote_addr' => '9.9.9.9']
        );
        $second = new Request(
            'GET',
            '/resource',
            [],
            ['X-Forwarded-For' => '5.6.7.8'],
            null,
            [],
            ['remote_addr' => '9.9.9.9']
        );

        $firstResponse = $middleware->handle($first, fn (Request $req): Response => Response::json(['ok' => true]));
        $secondResponse = $middleware->handle($second, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $firstResponse->status());
        $this->assertSame(429, $secondResponse->status());
    }

    public function testRequestsWithoutClientIdentityAreNotCollapsedIntoSharedBucket(): void
    {
        $middleware = RateLimit::perMinute(1);
        $request = new Request('GET', '/resource');

        $first = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $second = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $first->status());
        $this->assertSame(200, $second->status());
        $this->assertNull($first->header('X-RateLimit-Limit'));
        $this->assertNull($second->header('X-RateLimit-Limit'));
    }
}

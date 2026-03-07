<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\FilesystemRateLimitStore;
use Maia\Auth\InMemoryRateLimitStore;
use Maia\Auth\RateLimit;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    private InMemoryRateLimitStore $store;
    private string $filesystemStoreDir;

    protected function setUp(): void
    {
        $this->store = new InMemoryRateLimitStore();
        $this->filesystemStoreDir = sys_get_temp_dir() . '/maia_rate_limit_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->filesystemStoreDir);
    }

    public function testPerMinuteFactoryCreatesMiddleware(): void
    {
        $middleware = RateLimit::perMinute(5, [], $this->store);

        $this->assertInstanceOf(RateLimit::class, $middleware);
    }

    public function testBlocksAfterLimitExceeded(): void
    {
        $middleware = RateLimit::perMinute(2, [], $this->store);
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
        $middleware = RateLimit::perMinute(1, [], $this->store);

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
        $middleware = RateLimit::perMinute(1, ['10.0.0.10'], $this->store);

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
        $middleware = RateLimit::perMinute(1, [], $this->store);

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
        $middleware = RateLimit::perMinute(1, [], $this->store);
        $request = new Request('GET', '/resource');

        $first = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $second = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $first->status());
        $this->assertSame(200, $second->status());
        $this->assertNull($first->header('X-RateLimit-Limit'));
        $this->assertNull($second->header('X-RateLimit-Limit'));
    }

    public function testMultipleLimitersCanShareOneStoreWithoutInterferingWhenNamespaced(): void
    {
        $loginLimiter = RateLimit::perMinute(1, [], $this->store, 'login');
        $searchLimiter = RateLimit::perMinute(1, [], $this->store, 'search');
        $request = new Request('GET', '/resource', [], [], null, [], ['remote_addr' => '1.2.3.4']);

        $loginFirst = $loginLimiter->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $loginSecond = $loginLimiter->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $searchFirst = $searchLimiter->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $loginFirst->status());
        $this->assertSame(429, $loginSecond->status());
        $this->assertSame(200, $searchFirst->status());
    }

    public function testFilesystemStorePersistsAcrossMiddlewareInstances(): void
    {
        $store = new FilesystemRateLimitStore($this->filesystemStoreDir);
        $request = new Request('GET', '/resource', [], [], null, [], ['remote_addr' => '1.2.3.4']);

        $firstLimiter = RateLimit::perMinute(1, [], $store, 'api');
        $secondLimiter = RateLimit::perMinute(1, [], $store, 'api');

        $first = $firstLimiter->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));
        $second = $secondLimiter->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $first->status());
        $this->assertSame(429, $second->status());
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->deleteDirectory($target);
            } else {
                unlink($target);
            }
        }

        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Middleware;

use Maia\Core\Cache\FilesystemResponseCacheStore;
use Maia\Core\Cache\ResponseCacheStore;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\ResponseCacheMiddleware;
use PHPUnit\Framework\TestCase;

class ResponseCacheMiddlewareTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/maia-response-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $files = glob($this->cacheDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->cacheDir);
    }

    public function testCachesSuccessfulResponsesUsingFilesystemStore(): void
    {
        $store = new FilesystemResponseCacheStore($this->cacheDir);
        $middleware = new ResponseCacheMiddleware($store, 300, 'players');
        $request = new Request('GET', '/players', ['page' => '1'], [], null, []);
        $calls = 0;

        $first = $middleware->handle($request, function () use (&$calls): Response {
            $calls++;

            return Response::json(['players' => ['Saka']]);
        });

        $second = $middleware->handle($request, function () use (&$calls): Response {
            $calls++;

            return Response::json(['players' => ['Palmer']]);
        });

        $this->assertSame(1, $calls);
        $this->assertSame('MISS', $first->header('X-Response-Cache'));
        $this->assertSame('HIT', $second->header('X-Response-Cache'));
        $this->assertSame('{"players":["Saka"]}', $second->body());
    }

    public function testBypassesCacheForNonCacheableRequests(): void
    {
        $store = new InMemoryResponseCacheStore();
        $middleware = new ResponseCacheMiddleware($store);
        $request = new Request('POST', '/players', [], [], '{"name":"Saka"}', []);

        $response = $middleware->handle($request, fn (): Response => Response::json(['ok' => true], 201));

        $this->assertSame('BYPASS', $response->header('X-Response-Cache'));
        $this->assertNull($store->get('default:/players?'));
    }

    public function testUsesCustomCacheKeyResolver(): void
    {
        $store = new InMemoryResponseCacheStore();
        $middleware = new ResponseCacheMiddleware(
            store: $store,
            ttlSeconds: 60,
            namespace: 'scores',
            keyResolver: static fn (
                Request $request,
                string $namespace
            ): string => $namespace . ':' . $request->path()
        );
        $request = new Request('GET', '/scores', ['page' => '2'], [], null, []);

        $middleware->handle(
            $request,
            fn (): Response => Response::make('payload', 200, ['Content-Type' => 'text/plain'])
        );
        $response = $middleware->handle(
            $request,
            fn (): Response => Response::make('other', 200, ['Content-Type' => 'text/plain'])
        );

        $this->assertSame('HIT', $response->header('X-Response-Cache'));
        $this->assertSame('payload', $response->body());
    }

    public function testBypassesCacheWhenStoreUnavailable(): void
    {
        $store = new InMemoryResponseCacheStore(false);
        $middleware = new ResponseCacheMiddleware($store);
        $request = new Request('GET', '/health', [], [], null, []);

        $response = $middleware->handle($request, fn (): Response => Response::json(['ok' => true]));

        $this->assertSame('BYPASS', $response->header('X-Response-Cache'));
    }
}

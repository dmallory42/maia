<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\ApiKeyMiddleware;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    public function testRequestWithValidApiKeyPassesThrough(): void
    {
        $middleware = new ApiKeyMiddleware('valid-key');
        $request = new Request('GET', '/', [], ['X-API-Key' => 'valid-key'], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $response->status());
    }

    public function testRequestWithMissingApiKeyReturns401(): void
    {
        $middleware = new ApiKeyMiddleware('valid-key');
        $request = new Request('GET', '/', [], [], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(401, $response->status());
    }

    public function testRequestWithInvalidApiKeyReturns401(): void
    {
        $middleware = new ApiKeyMiddleware('valid-key');
        $request = new Request('GET', '/', [], ['X-API-Key' => 'invalid-key'], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(401, $response->status());
    }

    public function testRequestWithAnyAllowedKeyPasses(): void
    {
        $middleware = new ApiKeyMiddleware(['first-key', 'second-key']);
        $request = new Request('GET', '/', [], ['X-API-Key' => 'second-key'], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(200, $response->status());
    }
}

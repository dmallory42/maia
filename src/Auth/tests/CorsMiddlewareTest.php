<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\CorsMiddleware;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    public function testAddsCorsHeadersForAllowedOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://app.example.com']);
        $request = new Request('GET', '/', [], ['Origin' => 'https://app.example.com'], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame('https://app.example.com', $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->header('Vary'));
    }

    public function testRejectsDisallowedOrigins(): void
    {
        $middleware = new CorsMiddleware(['https://app.example.com']);
        $request = new Request('GET', '/', [], ['Origin' => 'https://evil.example.com'], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(403, $response->status());
    }

    public function testHandlesPreflightOptionsRequest(): void
    {
        $middleware = new CorsMiddleware(['https://app.example.com']);
        $request = new Request(
            'OPTIONS',
            '/users',
            [],
            [
                'Origin' => 'https://app.example.com',
                'Access-Control-Request-Method' => 'POST',
            ],
            null,
            []
        );

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame(204, $response->status());
        $this->assertSame('https://app.example.com', $response->header('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->header('Access-Control-Allow-Methods'));
    }
}

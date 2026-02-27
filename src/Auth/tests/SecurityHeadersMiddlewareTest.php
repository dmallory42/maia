<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\SecurityHeadersMiddleware;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAddsSecurityHeadersToResponse(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new Request('GET', '/', [], [], null, []);

        $response = $middleware->handle($request, fn (Request $req): Response => Response::json(['ok' => true]));

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('max-age=31536000; includeSubDomains', $response->header('Strict-Transport-Security'));
    }
}

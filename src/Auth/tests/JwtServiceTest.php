<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use InvalidArgumentException;
use Maia\Auth\Auth;
use Maia\Auth\JwtMiddleware;
use Maia\Auth\JwtService;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    public function testSupportsAllowedAlgorithms(): void
    {
        $service = new JwtService('test-secret-key-32-chars-minimum!!', 'hs256');
        $token = $service->encode(['sub' => 123]);
        $payload = $service->decode($token);

        $this->assertSame(123, (int) $payload->sub);
    }

    public function testRejectsUnsupportedAlgorithmsAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JwtService('test-secret-key-32-chars-minimum!!', 'RS256');
    }

    public function testAuthFactoryUsesValidatedAlgorithm(): void
    {
        $middleware = Auth::jwt('test-secret-key-32-chars-minimum!!', 'HS384');

        $this->assertInstanceOf(JwtMiddleware::class, $middleware);
    }

    public function testAuthFactoryRejectsUnsupportedAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Auth::jwt('test-secret-key-32-chars-minimum!!', 'none');
    }
}

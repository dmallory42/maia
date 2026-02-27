<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Exceptions;

use Maia\Core\Exceptions\ExceptionHandler;
use Maia\Core\Exceptions\NotFoundException;
use Maia\Core\Exceptions\UnauthorizedException;
use Maia\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    public function testNotFoundReturns404(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new NotFoundException('User not found'));

        $this->assertEquals(404, $response->status());
        $body = json_decode($response->body(), true);

        $this->assertTrue($body['error']);
        $this->assertEquals('User not found', $body['message']);
    }

    public function testValidationReturns422WithErrors(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $errors = ['email' => ['The email field is required.']];
        $response = $handler->handle(new ValidationException($errors));

        $this->assertEquals(422, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals($errors, $body['errors']);
    }

    public function testUnauthorizedReturns401(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new UnauthorizedException('Invalid token'));

        $this->assertEquals(401, $response->status());
    }

    public function testGenericExceptionReturns500InProduction(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new \RuntimeException('DB exploded'));

        $this->assertEquals(500, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals('Internal server error', $body['message']);
        $this->assertArrayNotHasKey('trace', $body);
    }

    public function testGenericExceptionReturnsDetailsInDebug(): void
    {
        $handler = new ExceptionHandler(debug: true);
        $response = $handler->handle(new \RuntimeException('DB exploded'));

        $this->assertEquals(500, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals('DB exploded', $body['message']);
        $this->assertArrayHasKey('exception', $body);
        $this->assertArrayHasKey('file', $body);
        $this->assertArrayHasKey('trace', $body);
    }
}

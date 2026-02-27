<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Http;

use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonResponse(): void
    {
        $response = Response::json(['name' => 'Mal'], 200);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('{"name":"Mal"}', $response->body());
        $this->assertEquals('application/json', $response->header('Content-Type'));
    }

    public function testErrorResponse(): void
    {
        $response = Response::error('Not found', 404);

        $this->assertEquals(404, $response->status());
        $body = json_decode($response->body(), true);

        $this->assertTrue($body['error']);
        $this->assertEquals('Not found', $body['message']);
    }

    public function testEmptyResponse(): void
    {
        $response = Response::empty(204);

        $this->assertEquals(204, $response->status());
        $this->assertEquals('', $response->body());
    }

    public function testCustomHeaders(): void
    {
        $response = Response::json(['ok' => true])
            ->withHeader('X-Custom', 'value');

        $this->assertEquals('value', $response->header('X-Custom'));
    }

    public function testDefaultJsonStatusIs200(): void
    {
        $response = Response::json(['ok' => true]);

        $this->assertEquals(200, $response->status());
    }
}

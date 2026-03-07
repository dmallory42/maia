<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Http;

use Maia\Core\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RequestTest extends TestCase
{
    public function testCreatesFromGlobals(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/users/42',
            query: ['page' => '2'],
            headers: ['Content-Type' => 'application/json'],
            body: null,
            routeParams: []
        );

        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/users/42', $request->path());
        $this->assertEquals('2', $request->query('page'));
        $this->assertNull($request->query('missing'));
        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    public function testJsonBodyParsing(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/users',
            query: [],
            headers: ['Content-Type' => 'application/json'],
            body: '{"name": "Mal", "email": "mal@test.com"}',
            routeParams: []
        );

        $this->assertEquals(['name' => 'Mal', 'email' => 'mal@test.com'], $request->body());
    }

    public function testRouteParams(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/users/42',
            query: [],
            headers: [],
            body: null,
            routeParams: ['id' => '42']
        );

        $this->assertEquals('42', $request->param('id'));
    }

    public function testBearerTokenExtraction(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/',
            query: [],
            headers: ['Authorization' => 'Bearer abc123'],
            body: null,
            routeParams: []
        );

        $this->assertEquals('abc123', $request->bearerToken());
    }

    public function testWithAttributeReturnsNewInstance(): void
    {
        $request = new Request('GET', '/', [], [], null, []);
        $new = $request->withAttribute('user', ['id' => 1]);

        $this->assertNotSame($request, $new);
        $this->assertNull($request->attribute('user'));
        $this->assertEquals(['id' => 1], $new->attribute('user'));
    }

    public function testUserShortcut(): void
    {
        $request = new Request('GET', '/', [], [], null, []);
        $request = $request->withAttribute('user', (object) ['name' => 'Mal']);

        $this->assertEquals('Mal', $request->user()->name);
    }

    public function testHeaderCaseInsensitive(): void
    {
        $request = new Request('GET', '/', [], ['Content-Type' => 'application/json'], null, []);

        $this->assertEquals('application/json', $request->header('content-type'));
        $this->assertEquals('application/json', $request->header('CONTENT-TYPE'));
    }

    public function testQueryParamsReturnsAllParameters(): void
    {
        $request = new Request('GET', '/users', ['page' => '2', 'filter' => 'active'], [], null, []);

        $this->assertSame([
            'page' => '2',
            'filter' => 'active',
        ], $request->queryParams());
    }

    public function testRemoteAddressReturnsCapturedServerAddress(): void
    {
        $request = new Request('GET', '/', [], [], null, [], ['remote_addr' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $request->remoteAddress());
    }

    public function testFallbackHeaderCaptureIncludesContentTypeAndLength(): void
    {
        $method = new ReflectionMethod(Request::class, 'readHeadersFromServer');
        $method->setAccessible(true);

        $headers = $method->invoke(null, [
            'HTTP_X_TRACE_ID' => 'trace-123',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '27',
        ]);

        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('27', $headers['Content-Length']);
        $this->assertSame('trace-123', $headers['X-Trace-Id']);
    }

    public function testFallbackCapturedHeadersStillEnableJsonBodyParsing(): void
    {
        $method = new ReflectionMethod(Request::class, 'readHeadersFromServer');
        $method->setAccessible(true);

        $headers = $method->invoke(null, [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $request = new Request('POST', '/users', [], $headers, '{"name":"Mal"}', []);

        $this->assertSame(['name' => 'Mal'], $request->body());
    }
}

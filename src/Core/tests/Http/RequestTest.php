<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Http;

use Maia\Core\Http\Request;
use PHPUnit\Framework\TestCase;

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
}

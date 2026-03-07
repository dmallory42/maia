<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Testing;

use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
use Maia\Core\Testing\TestCase;
use Maia\Core\Testing\TestResponse;

#[Controller('/testing')]
class TestingController
{
    #[Route('/hello', method: 'GET')]
    public function hello(): Response
    {
        return Response::json(['message' => 'hello']);
    }

    #[Route('/echo', method: 'POST')]
    public function echo(Request $request): Response
    {
        return Response::json([
            'method' => $request->method(),
            'body' => $request->body(),
        ]);
    }

    #[Route('/auth', method: 'GET')]
    public function auth(Request $request): Response
    {
        return Response::json(['authorization' => $request->header('Authorization')]);
    }

    #[Route('/echo', method: 'PUT')]
    public function put(Request $request): Response
    {
        return Response::json([
            'method' => $request->method(),
            'body' => $request->body(),
        ]);
    }

    #[Route('/echo', method: 'PATCH')]
    public function patch(Request $request): Response
    {
        return Response::json([
            'method' => $request->method(),
            'body' => $request->body(),
        ]);
    }

    #[Route('/echo', method: 'DELETE')]
    public function delete(Request $request): Response
    {
        return Response::json([
            'method' => $request->method(),
            'body' => $request->body(),
        ]);
    }
}

abstract class TestCaseHarness extends TestCase
{
    protected function controllers(): array
    {
        return [TestingController::class];
    }
}

class TestCaseTest extends TestCaseHarness
{
    public function testGetReturnsTestResponse(): void
    {
        $response = $this->get('/testing/hello');

        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertStatus(200);
    }

    public function testPostSendsJsonBody(): void
    {
        $response = $this->post('/testing/echo', ['name' => 'Mal']);

        $response->assertStatus(200);
        $this->assertSame('POST', $response->json()['method']);
        $this->assertSame(['name' => 'Mal'], $response->json()['body']);
    }

    public function testPutSendsJsonBody(): void
    {
        $response = $this->put('/testing/echo', ['name' => 'Mal']);

        $response->assertStatus(200);
        $this->assertSame('PUT', $response->json()['method']);
        $this->assertSame(['name' => 'Mal'], $response->json()['body']);
    }

    public function testPatchSendsJsonBody(): void
    {
        $response = $this->patch('/testing/echo', ['name' => 'Mal']);

        $response->assertStatus(200);
        $this->assertSame('PATCH', $response->json()['method']);
        $this->assertSame(['name' => 'Mal'], $response->json()['body']);
    }

    public function testDeleteSendsJsonBody(): void
    {
        $response = $this->delete('/testing/echo', ['name' => 'Mal']);

        $response->assertStatus(200);
        $this->assertSame('DELETE', $response->json()['method']);
        $this->assertSame(['name' => 'Mal'], $response->json()['body']);
    }

    public function testWithTokenSetsAuthorizationHeader(): void
    {
        $response = $this->withToken('abc123')->get('/testing/auth');

        $response->assertStatus(200);
        $this->assertSame('Bearer abc123', $response->json()['authorization']);
    }

    public function testAssertJsonStructureValidatesShape(): void
    {
        $this->get('/testing/hello')->assertJsonStructure(['message']);
    }

    public function testResolveReturnsContainerInstance(): void
    {
        $instance = $this->resolve(\stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testAssertDatabaseHasChecksRows(): void
    {
        $this->db()->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $this->db()->execute('INSERT INTO users (email) VALUES (?)', ['mal@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'mal@example.com']);
    }
}

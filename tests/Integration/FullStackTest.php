<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maia\Auth\JwtService;
use Maia\Auth\Validator;
use Maia\Core\App;
use Maia\Core\Exceptions\ValidationException;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
use Maia\Orm\Attributes\Table;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use PHPUnit\Framework\TestCase;

#[Table('users')]
class FullStackUser extends Model
{
    public int $id;
    public string $email;
}

class FullStackUserService
{
    /** @param array<string, mixed> $payload */
    public function create(array $payload): FullStackUser
    {
        return FullStackUser::create([
            'email' => (string) $payload['email'],
        ]);
    }
}

#[Controller('/users')]
class FullStackUserController
{
    public function __construct(
        private FullStackUserService $service,
        private Validator $validator
    ) {
    }

    #[Route('/', method: 'POST', middleware: [\Maia\Auth\JwtMiddleware::class])]
    public function create(Request $request): Response
    {
        $payload = $request->body();
        $data = is_array($payload) ? $payload : [];

        $errors = $this->validator->validate($data, [
            'email' => 'required|email',
        ]);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->service->create($data);

        return Response::json([
            'id' => $user->id,
            'email' => $user->email,
        ], 201);
    }
}

class FullStackTest extends TestCase
{
    private Connection $connection;
    private App $app;
    private JwtService $jwt;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        FullStackUser::setConnection($this->connection);

        $this->jwt = new JwtService('test-secret-key-32-chars-minimum!!');

        $this->app = App::create();
        $this->app->container()->instance(JwtService::class, $this->jwt);
        $this->app->container()->instance(Validator::class, new Validator());
        $this->app->registerController(FullStackUserController::class);
    }

    public function testFullRequestLifecycleCreatesUser(): void
    {
        $token = $this->jwt->encode(['sub' => 123]);
        $request = new Request(
            'POST',
            '/users',
            [],
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            '{"email":"mal@example.com"}',
            []
        );

        $response = $this->app->handle($request);

        $this->assertSame(201, $response->status());
        $payload = json_decode($response->body(), true);
        $this->assertSame('mal@example.com', $payload['email']);

        $rows = $this->connection->query('SELECT email FROM users WHERE email = ?', ['mal@example.com']);
        $this->assertCount(1, $rows);
    }

    public function testReturns401WhenAuthMissing(): void
    {
        $request = new Request('POST', '/users', [], ['Content-Type' => 'application/json'], '{"email":"x@y.com"}', []);

        $response = $this->app->handle($request);

        $this->assertSame(401, $response->status());
    }

    public function testReturns422WhenValidationFails(): void
    {
        $token = $this->jwt->encode(['sub' => 123]);
        $request = new Request(
            'POST',
            '/users',
            [],
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            '{"email":"not-an-email"}',
            []
        );

        $response = $this->app->handle($request);

        $this->assertSame(422, $response->status());
    }

    public function testReturns404ForUnknownRoute(): void
    {
        $request = new Request('GET', '/missing', [], [], null, []);

        $response = $this->app->handle($request);

        $this->assertSame(404, $response->status());
    }
}

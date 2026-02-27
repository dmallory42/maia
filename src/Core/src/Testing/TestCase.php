<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Auth\JwtService;
use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected App $app;
    protected Connection $connection;

    /** @var array<string, string> */
    private array $headers = [];

    protected string $jwtSecret = 'test-secret-key-32-chars-minimum!!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection('sqlite::memory:');
        Model::setConnection($this->connection);

        $this->app = App::create();

        foreach ($this->controllers() as $controller) {
            $this->app->registerController($controller);
        }
    }

    /**
     * @return array<int, class-string>
     */
    protected function controllers(): array
    {
        return [];
    }

    public function withToken(string $token): static
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;

        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function get(string $path): TestResponse
    {
        $request = new Request('GET', $path, [], $this->headers, null, []);

        return $this->send($request);
    }

    /** @param array<string, mixed> $data */
    public function post(string $path, array $data = []): TestResponse
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';

        $request = new Request('POST', $path, [], $headers, json_encode($data) ?: '{}', []);

        return $this->send($request);
    }

    public function resolve(string $class): object
    {
        return $this->app->container()->resolve($class);
    }

    /** @param array<string, mixed> $criteria */
    public function assertDatabaseHas(string $table, array $criteria): void
    {
        $clauses = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $clauses[] = $column . ' = ?';
            $params[] = $value;
        }

        $sql = sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE %s', $table, implode(' AND ', $clauses));
        $rows = $this->connection->query($sql, $params);

        $this->assertSame(1, (int) ($rows[0]['aggregate'] ?? 0));
    }

    /** @param array<string, mixed> $payload */
    public function generateJwt(array $payload): string
    {
        return (new JwtService($this->jwtSecret))->encode($payload);
    }

    protected function db(): Connection
    {
        return $this->connection;
    }

    private function send(Request $request): TestResponse
    {
        $response = $this->app->handle($request);

        return new TestResponse($response, $this);
    }
}

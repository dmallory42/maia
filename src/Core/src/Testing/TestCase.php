<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Auth\JwtService;
use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * TestCase defines a framework component for this package.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected App $app;
    protected Connection $connection;

    /** @var array<string, string> */
    private array $headers = [];

    protected string $jwtSecret = 'test-secret-key-32-chars-minimum!!';

    /**
     * Set up and return void.
     * @return void Output value.
     */
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
     * Controllers and return array.
     * @return array Output value.
     */
    protected function controllers(): array
    {
        return [];
    }

    /**
     * With token and return static.
     * @param string $token Input value.
     * @return static Output value.
     */
    public function withToken(string $token): static
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;

        return $this;
    }

    /**
     * With header and return static.
     * @param string $name Input value.
     * @param string $value Input value.
     * @return static Output value.
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Get and return TestResponse.
     * @param string $path Input value.
     * @return TestResponse Output value.
     */
    public function get(string $path): TestResponse
    {
        $request = new Request('GET', $path, [], $this->headers, null, []);

        return $this->send($request);
    }

    /**
     * Post and return TestResponse.
     * @param string $path Input value.
     * @param array $data Input value.
     * @return TestResponse Output value.
     */
    public function post(string $path, array $data = []): TestResponse
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';

        $request = new Request('POST', $path, [], $headers, json_encode($data) ?: '{}', []);

        return $this->send($request);
    }

    /**
     * Resolve and return object.
     * @param string $class Input value.
     * @return object Output value.
     */
    public function resolve(string $class): object
    {
        return $this->app->container()->resolve($class);
    }

    /**
     * Assert database has and return void.
     * @param string $table Input value.
     * @param array $criteria Input value.
     * @return void Output value.
     */
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

    /**
     * Generate jwt and return string.
     * @param array $payload Input value.
     * @return string Output value.
     */
    public function generateJwt(array $payload): string
    {
        return (new JwtService($this->jwtSecret))->encode($payload);
    }

    /**
     * Db and return Connection.
     * @return Connection Output value.
     */
    protected function db(): Connection
    {
        return $this->connection;
    }

    /**
     * Send and return TestResponse.
     * @param Request $request Input value.
     * @return TestResponse Output value.
     */
    private function send(Request $request): TestResponse
    {
        $response = $this->app->handle($request);

        return new TestResponse($response, $this);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base integration-test harness for Maia applications with helpers for HTTP requests, DI, and database assertions.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected App $app;
    protected Connection $connection;

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * Boot an in-memory application and SQLite connection before each test.
     * @return void
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
     * Return the controller classes that should be registered for the test app.
     * @return array Controller class names.
     */
    protected function controllers(): array
    {
        return [];
    }

    /**
     * Add a Bearer token to subsequent test requests.
     * @param string $token JWT token value.
     * @return static Test case for fluent chaining.
     */
    public function withToken(string $token): static
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;

        return $this;
    }

    /**
     * Add a header to subsequent test requests.
     * @param string $name Header name.
     * @param string $value Header value.
     * @return static Test case for fluent chaining.
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Send a GET request through the test application.
     * @param string $path Request path.
     * @return TestResponse Wrapped response with assertion helpers.
     */
    public function get(string $path): TestResponse
    {
        $request = new Request('GET', $path, [], $this->headers, null, []);

        return $this->send($request);
    }

    /**
     * Send a JSON POST request through the test application.
     * @param string $path Request path.
     * @param array $data JSON payload to send.
     * @return TestResponse Wrapped response with assertion helpers.
     */
    public function post(string $path, array $data = []): TestResponse
    {
        return $this->sendJsonRequest('POST', $path, $data);
    }

    /**
     * Put and return TestResponse.
     * @param string $path Input value.
     * @param array $data Input value.
     * @return TestResponse Output value.
     */
    public function put(string $path, array $data = []): TestResponse
    {
        return $this->sendJsonRequest('PUT', $path, $data);
    }

    /**
     * Patch and return TestResponse.
     * @param string $path Input value.
     * @param array $data Input value.
     * @return TestResponse Output value.
     */
    public function patch(string $path, array $data = []): TestResponse
    {
        return $this->sendJsonRequest('PATCH', $path, $data);
    }

    /**
     * Delete and return TestResponse.
     * @param string $path Input value.
     * @param array $data Input value.
     * @return TestResponse Output value.
     */
    public function delete(string $path, array $data = []): TestResponse
    {
        return $this->sendJsonRequest('DELETE', $path, $data);
    }

    /**
     * Send json request and return TestResponse.
     * @param string $method Input value.
     * @param string $path Input value.
     * @param array $data Input value.
     * @return TestResponse Output value.
     */
    private function sendJsonRequest(string $method, string $path, array $data = []): TestResponse
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';

        $request = new Request($method, $path, [], $headers, json_encode($data) ?: '{}', []);

        return $this->send($request);
    }

    /**
     * Resolve a service from the application container.
     * @param string $class Fully qualified class name to resolve.
     * @return object Resolved service instance.
     */
    public function resolve(string $class): object
    {
        return $this->app->container()->resolve($class);
    }

    /**
     * Assert that exactly one database row matches the given criteria.
     * @param string $table Table name.
     * @param array $criteria Column-value pairs to match.
     * @return void
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
     * Return the test database connection.
     * @return Connection In-memory SQLite connection.
     */
    protected function db(): Connection
    {
        return $this->connection;
    }

    /**
     * Dispatch a prepared request through the application and wrap the response.
     * @param Request $request Request to send.
     * @return TestResponse Wrapped response with assertion helpers.
     */
    private function send(Request $request): TestResponse
    {
        $response = $this->app->handle($request);

        return new TestResponse($response, $this);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * TestResponse defines a framework component for this package.
 */
class TestResponse
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param Response $response Input value.
     * @param PHPUnitTestCase $testCase Input value.
     * @return void Output value.
     */
    public function __construct(
        private Response $response,
        private PHPUnitTestCase $testCase
    ) {
    }

    /**
     * Response and return Response.
     * @return Response Output value.
     */
    public function response(): Response
    {
        return $this->response;
    }

    /**
     * Assert status and return self.
     * @param int $status Input value.
     * @return self Output value.
     */
    public function assertStatus(int $status): self
    {
        $this->testCase->assertSame($status, $this->response->status());

        return $this;
    }

    /**
     * Assert json structure and return self.
     * @param array $structure Input value.
     * @return self Output value.
     */
    public function assertJsonStructure(array $structure): self
    {
        $decoded = $this->json();
        $this->assertStructure($decoded, $structure);

        return $this;
    }

    /**
     * Json and return array.
     * @return array Output value.
     */
    public function json(): array
    {
        $decoded = json_decode($this->response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Assert structure and return void.
     * @param mixed $payload Input value.
     * @param array $structure Input value.
     * @return void Output value.
     */
    private function assertStructure(mixed $payload, array $structure): void
    {
        $this->testCase->assertIsArray($payload);

        foreach ($structure as $key => $value) {
            if (is_int($key)) {
                $field = (string) $value;
                $nested = null;
            } else {
                $field = (string) $key;
                $nested = $value;
            }

            $this->testCase->assertArrayHasKey($field, $payload);

            if (is_array($nested)) {
                $this->assertStructure($payload[$field], $nested);
            }
        }
    }
}

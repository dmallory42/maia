<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Test helper wrapper around Response with common assertions and JSON decoding.
 */
class TestResponse
{
    /**
     * Pair a framework response with the PHPUnit test case that will assert on it.
     * @param Response $response Response under test.
     * @param PHPUnitTestCase $testCase Active PHPUnit test case.
     * @return void
     */
    public function __construct(
        private Response $response,
        private PHPUnitTestCase $testCase
    ) {
    }

    /**
     * Assert that the response has the expected HTTP status code.
     * @param int $status Expected HTTP status code.
     * @return self Test response for fluent chaining.
     */
    public function assertStatus(int $status): self
    {
        $this->testCase->assertSame($status, $this->response->status());

        return $this;
    }

    /**
     * Assert that the decoded JSON payload contains the expected nested structure.
     * @param array $structure Expected shape definition.
     * @return self Test response for fluent chaining.
     */
    public function assertJsonStructure(array $structure): self
    {
        $decoded = $this->json();
        $this->assertStructure($decoded, $structure);

        return $this;
    }

    /**
     * Decode the response body as JSON.
     * @return array Decoded JSON payload, or an empty array if decoding fails.
     */
    public function json(): array
    {
        $decoded = json_decode($this->response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Recursively assert that a payload matches the expected structure shape.
     * @param mixed $payload Decoded JSON payload or nested fragment.
     * @param array $structure Expected shape definition.
     * @return void
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

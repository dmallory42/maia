<?php

declare(strict_types=1);

namespace Maia\Core\Testing;

use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestResponse
{
    public function __construct(
        private Response $response,
        private PHPUnitTestCase $testCase
    ) {
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function assertStatus(int $status): self
    {
        $this->testCase->assertSame($status, $this->response->status());

        return $this;
    }

    /** @param array<int|string, mixed> $structure */
    public function assertJsonStructure(array $structure): self
    {
        $decoded = $this->json();
        $this->assertStructure($decoded, $structure);

        return $this;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $payload
     * @param array<int|string, mixed> $structure
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

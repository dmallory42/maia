<?php

declare(strict_types=1);

namespace Maia\Core\Http;

/**
 * HTTP response value object with helpers for JSON responses and immutable header updates.
 */
class Response
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * Build a response with status code, body, and headers.
     * @param int $status HTTP status code.
     * @param string $body Response body string.
     * @param array $headers Response headers.
     * @return void
     */
    private function __construct(
        private int $status,
        private string $body,
        array $headers = []
    ) {
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[$this->normalizeHeaderName($name)] = $value;
        }
    }

    /**
     * Create a plain response.
     * @param string $body Response body string.
     * @param int $status HTTP status code.
     * @param array<string, string> $headers Response headers.
     * @return self New response instance.
     */
    public static function make(string $body = '', int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    /**
     * Create a JSON response.
     * @param mixed $data Value to encode as JSON.
     * @param int $status HTTP status code.
     * @return self New JSON response instance.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            $encoded = 'null';
        }

        return new self($status, $encoded, ['Content-Type' => 'application/json']);
    }

    /**
     * Create the framework's standard JSON error envelope.
     * @param string $message Error message exposed to the client.
     * @param int $status HTTP status code.
     * @return self New JSON error response.
     */
    public static function error(string $message, int $status): self
    {
        return self::json([
            'error' => true,
            'message' => $message,
        ], $status);
    }

    public static function empty(int $status = 204): self
    {
        return new self($status, '');
    }

    /**
     * Return the HTTP status code.
     * @return int HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Return the raw response body.
     * @return string Response body string.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Retrieve a response header case-insensitively.
     * @param string $name Header name.
     * @return string|null Header value, or null if absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[$this->normalizeHeaderName($name)] ?? null;
    }

    /**
     * Return the full response header map.
     * @return array Response headers keyed by normalized header name.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Return a cloned response with an added or replaced header.
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self Cloned response carrying the header.
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$this->normalizeHeaderName($name)] = $value;
        return $clone;
    }

    /**
     * Emit the status code, headers, and body to PHP's output buffer.
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }

    /**
     * Normalize a header name to canonical Title-Case.
     * @param string $name Raw header name.
     * @return string Normalized header name.
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower(trim($name));
        $parts = explode('-', $name);
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);

        return implode('-', $parts);
    }
}

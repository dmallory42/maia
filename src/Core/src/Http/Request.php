<?php

declare(strict_types=1);

namespace Maia\Core\Http;

class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, string> */
    private array $routeParams;

    /** @var array<string, mixed> */
    private array $attributes;

    private ?string $rawBody;
    private mixed $decodedBody = null;
    private bool $bodyDecoded = false;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private string $method,
        private string $path,
        array $query = [],
        array $headers = [],
        ?string $body = null,
        array $routeParams = [],
        array $attributes = []
    ) {
        $this->method = strtoupper($method);
        $this->query = $query;
        $this->headers = $this->normalizeHeaders($headers);
        $this->rawBody = $body;
        $this->routeParams = $routeParams;
        $this->attributes = $attributes;
    }

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $body = file_get_contents('php://input');

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            headers: self::readHeaders(),
            body: $body === false ? null : $body,
            routeParams: []
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function body(): mixed
    {
        if ($this->bodyDecoded) {
            return $this->decodedBody;
        }

        $this->bodyDecoded = true;

        if ($this->rawBody === null || $this->rawBody === '') {
            $this->decodedBody = null;
            return $this->decodedBody;
        }

        $contentType = $this->header('content-type', '');
        if (is_string($contentType) && str_contains(strtolower($contentType), 'application/json')) {
            $decoded = json_decode($this->rawBody, true);
            $this->decodedBody = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            return $this->decodedBody;
        }

        $this->decodedBody = $this->rawBody;
        return $this->decodedBody;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('authorization');
        if (!is_string($authorization)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function user(): mixed
    {
        return $this->attribute('user');
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;
        return $clone;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = is_string($value) ? $value : (string) $value;
        }

        return $normalized;
    }

    /** @return array<string, string> */
    private static function readHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== false) {
                $normalized = [];
                foreach ($headers as $key => $value) {
                    $normalized[(string) $key] = is_string($value) ? $value : (string) $value;
                }

                return $normalized;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = str_replace('_', '-', strtolower(substr($key, 5)));
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            $headers[$name] = is_string($value) ? $value : (string) $value;
        }

        return $headers;
    }
}

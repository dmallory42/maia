<?php

declare(strict_types=1);

namespace Maia\Core\Http;

/**
 * Request defines a framework component for this package.
 */
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
     * Create an instance with configured dependencies and defaults.
     * @param string $method Input value.
     * @param string $path Input value.
     * @param array $query Input value.
     * @param array $headers Input value.
     * @param string|null $body Input value.
     * @param array $routeParams Input value.
     * @param array $attributes Input value.
     * @return void Output value.
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

    /**
     * Capture and return self.
     * @return self Output value.
     */
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

    /**
     * Method and return string.
     * @return string Output value.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Path and return string.
     * @return string Output value.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Query and return mixed.
     * @param string $key Input value.
     * @param mixed $default Input value.
     * @return mixed Output value.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Query params and return array.
     * @return array<string, mixed> Output value.
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    /**
     * Param and return mixed.
     * @param string $key Input value.
     * @param mixed $default Input value.
     * @return mixed Output value.
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Body and return mixed.
     * @return mixed Output value.
     */
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

    /**
     * Header and return mixed.
     * @param string $key Input value.
     * @param mixed $default Input value.
     * @return mixed Output value.
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Bearer token and return string|null.
     * @return string|null Output value.
     */
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

    /**
     * With attribute and return self.
     * @param string $key Input value.
     * @param mixed $value Input value.
     * @return self Output value.
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Attribute and return mixed.
     * @param string $key Input value.
     * @param mixed $default Input value.
     * @return mixed Output value.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * User and return mixed.
     * @return mixed Output value.
     */
    public function user(): mixed
    {
        return $this->attribute('user');
    }

    /**
     * With route params and return self.
     * @param array $routeParams Input value.
     * @return self Output value.
     */
    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;
        return $clone;
    }

    /**
     * Normalize headers and return array.
     * @param array $headers Input value.
     * @return array Output value.
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = is_string($value) ? $value : (string) $value;
        }

        return $normalized;
    }

    /**
     * Read headers and return array.
     * @return array Output value.
     */
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

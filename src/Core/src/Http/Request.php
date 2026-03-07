<?php

declare(strict_types=1);

namespace Maia\Core\Http;

/**
 * Immutable-ish HTTP request value object with helpers for headers, query params, body decoding, and route attributes.
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
     * Build a request from explicit HTTP method, path, headers, body, and route metadata.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $path Request path.
     * @param array $query Query string parameters.
     * @param array $headers Request headers.
     * @param string|null $body Raw request body.
     * @param array $routeParams Named route parameters extracted from the URL.
     * @param array $attributes Additional per-request attributes.
     * @return void
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
     * Capture a request from PHP superglobals.
     * @return self Request instance populated from $_SERVER, $_GET, and php://input.
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
     * Return the normalized HTTP method.
     * @return string Uppercased HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Return the request path.
     * @return string URL path without query string.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Retrieve a query-string value with an optional default.
     * @param string $key Query parameter name.
     * @param mixed $default Default value when the key is absent.
     * @return mixed Query parameter value or the provided default.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Return all query-string parameters.
     * @return array<string, mixed> Query parameters.
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    /**
     * Retrieve a named route parameter with an optional default.
     * @param string $key Route parameter name.
     * @param mixed $default Default value when the key is absent.
     * @return mixed Route parameter value or the provided default.
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Return the decoded request body, parsing JSON when the content type indicates it.
     * @return mixed Decoded JSON payload, raw string body, or null when empty/invalid.
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
     * Retrieve a request header case-insensitively.
     * @param string $key Header name.
     * @param mixed $default Default value when the header is absent.
     * @return mixed Header value or the provided default.
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     * @return string|null Bearer token, or null when missing/malformed.
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
     * Return a cloned request with an additional attribute value.
     * @param string $key Attribute name.
     * @param mixed $value Attribute value.
     * @return self Cloned request carrying the new attribute.
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Retrieve a request attribute with an optional default.
     * @param string $key Attribute name.
     * @param mixed $default Default value when the attribute is absent.
     * @return mixed Attribute value or the provided default.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Convenience accessor for the authenticated user payload.
     * @return mixed User attribute value, if set.
     */
    public function user(): mixed
    {
        return $this->attribute('user');
    }

    /**
     * Return a cloned request with route parameters attached.
     * @param array $routeParams Named route parameters.
     * @return self Cloned request carrying the route params.
     */
    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;
        return $clone;
    }

    /**
     * Normalize header names to lowercase for case-insensitive lookups.
     * @param array $headers Raw header map.
     * @return array Normalized header map.
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
     * Read request headers from PHP's runtime environment.
     * @return array Header map from getallheaders() or $_SERVER.
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

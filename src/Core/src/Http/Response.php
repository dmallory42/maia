<?php

declare(strict_types=1);

namespace Maia\Core\Http;

/**
 * Response defines a framework component for this package.
 */
class Response
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param int $status Input value.
     * @param string $body Input value.
     * @param array $headers Input value.
     * @return void Output value.
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
     * Make and return self.
     * @param string $body Input value.
     * @param int $status Input value.
     * @param array<string, string> $headers Input value.
     * @return self Output value.
     */
    public static function make(string $body = '', int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    /**
     * Json and return self.
     * @param mixed $data Input value.
     * @param int $status Input value.
     * @return self Output value.
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
     * Error and return self.
     * @param string $message Input value.
     * @param int $status Input value.
     * @return self Output value.
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
     * Status and return int.
     * @return int Output value.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Body and return string.
     * @return string Output value.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Header and return string|null.
     * @param string $name Input value.
     * @return string|null Output value.
     */
    public function header(string $name): ?string
    {
        return $this->headers[$this->normalizeHeaderName($name)] ?? null;
    }

    /**
     * Headers and return array.
     * @return array Output value.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * With header and return self.
     * @param string $name Input value.
     * @param string $value Input value.
     * @return self Output value.
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$this->normalizeHeaderName($name)] = $value;
        return $clone;
    }

    /**
     * Send and return void.
     * @return void Output value.
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
     * Normalize header name and return string.
     * @param string $name Input value.
     * @return string Output value.
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower(trim($name));
        $parts = explode('-', $name);
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);

        return implode('-', $parts);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Http;

class Response
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
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

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            $encoded = 'null';
        }

        return new self($status, $encoded, ['Content-Type' => 'application/json']);
    }

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

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$this->normalizeHeaderName($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$this->normalizeHeaderName($name)] = $value;
        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }

    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower(trim($name));
        $parts = explode('-', $name);
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);

        return implode('-', $parts);
    }
}

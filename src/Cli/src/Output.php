<?php

declare(strict_types=1);

namespace Maia\Cli;

class Output
{
    private string $buffer = '';

    public function __construct(private bool $json = false)
    {
    }

    public function line(string $message): void
    {
        $this->append($message);
    }

    /** @param array<string, mixed> $payload */
    public function json(array $payload): void
    {
        $encoded = json_encode($payload);
        if ($encoded === false) {
            $encoded = '{}';
        }

        $this->append($encoded);
    }

    public function error(string $message): void
    {
        if ($this->json) {
            $this->json([
                'error' => true,
                'message' => $message,
            ]);

            return;
        }

        $this->append('Error: ' . $message);
    }

    public function buffer(): string
    {
        return $this->buffer;
    }

    public function isJson(): bool
    {
        return $this->json;
    }

    private function append(string $message): void
    {
        $this->buffer .= $message . PHP_EOL;
    }
}

<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * Output defines a framework component for this package.
 */
class Output
{
    private string $buffer = '';

    /**
     * Create an instance with configured dependencies and defaults.
     * @param bool $json Input value.
     * @param bool $emit Input value.
     * @return void Output value.
     */
    public function __construct(
        private bool $json = false,
        private bool $emit = false
    )
    {
    }

    /**
     * Line and return void.
     * @param string $message Input value.
     * @return void Output value.
     */
    public function line(string $message): void
    {
        $this->append($message);
    }

    /**
     * Json and return void.
     * @param array $payload Input value.
     * @return void Output value.
     */
    public function json(array $payload): void
    {
        $encoded = json_encode($payload);
        if ($encoded === false) {
            $encoded = '{}';
        }

        $this->append($encoded);
    }

    /**
     * Error and return void.
     * @param string $message Input value.
     * @return void Output value.
     */
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

    /**
     * Buffer and return string.
     * @return string Output value.
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Is json and return bool.
     * @return bool Output value.
     */
    public function isJson(): bool
    {
        return $this->json;
    }

    /**
     * Append and return void.
     * @param string $message Input value.
     * @return void Output value.
     */
    private function append(string $message): void
    {
        $this->buffer .= $message . PHP_EOL;

        if ($this->emit) {
            fwrite(STDOUT, $message . PHP_EOL);
        }
    }
}

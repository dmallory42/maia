<?php

declare(strict_types=1);

namespace Maia\Cli;

/**
 * Collects and optionally emits CLI output as plain text or JSON.
 */
class Output
{
    private string $buffer = '';

    /**
     * Configure the output mode and emission behavior.
     * @param bool $json Whether commands should format output as JSON.
     * @param bool $emit Whether to write output to STDOUT immediately in addition to buffering.
     * @return void
     */
    public function __construct(
        private bool $json = false,
        private bool $emit = false
    ) {
    }

    /**
     * Write a single line of plain-text output.
     * @param string $message The text to output.
     * @return void
     */
    public function line(string $message): void
    {
        $this->append($message);
    }

    /**
     * Write a JSON-encoded payload as a single line of output.
     * @param array $payload Associative array to encode as JSON.
     * @return void
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
     * Write an error message, formatted as JSON when in JSON mode.
     * @param string $message The error description to display.
     * @return void
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
     * Return all buffered output accumulated so far.
     * @return string The complete output buffer contents.
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Check whether this output instance is configured for JSON mode.
     * @return bool True if JSON output was requested.
     */
    public function isJson(): bool
    {
        return $this->json;
    }

    /**
     * Append a message to the buffer and optionally write it to STDOUT.
     * @param string $message The text to append.
     * @return void
     */
    private function append(string $message): void
    {
        $this->buffer .= $message . PHP_EOL;

        if ($this->emit) {
            fwrite(STDOUT, $message . PHP_EOL);
        }
    }
}

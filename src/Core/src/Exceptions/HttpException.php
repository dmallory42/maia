<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

use RuntimeException;

/**
 * Base exception type for errors that map directly to an HTTP status code.
 */
class HttpException extends RuntimeException
{
    /**
     * Build an HTTP exception with status code, message, and optional previous exception.
     * @param int $status HTTP status code to return.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
     */
    public function __construct(
        private int $status,
        string $message = 'HTTP error',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Return the HTTP status code associated with this exception.
     * @return int HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }
}

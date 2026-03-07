<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * HTTP 409 exception for resource conflicts.
 */
class ConflictException extends HttpException
{
    /**
     * Build a conflict exception with an optional message and previous exception.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
     */
    public function __construct(string $message = 'Conflict', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(409, $message, $code, $previous);
    }
}

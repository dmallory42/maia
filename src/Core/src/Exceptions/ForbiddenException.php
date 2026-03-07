<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * HTTP 403 exception for forbidden operations.
 */
class ForbiddenException extends HttpException
{
    /**
     * Build a forbidden exception with an optional message and previous exception.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
     */
    public function __construct(string $message = 'Forbidden', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $code, $previous);
    }
}

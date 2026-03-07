<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * HTTP 401 exception for unauthenticated requests.
 */
class UnauthorizedException extends HttpException
{
    /**
     * Build an unauthorized exception with an optional message and previous exception.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
     */
    public function __construct(string $message = 'Unauthorized', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(401, $message, $code, $previous);
    }
}

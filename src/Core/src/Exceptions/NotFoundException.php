<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * HTTP 404 exception for missing resources or routes.
 */
class NotFoundException extends HttpException
{
    /**
     * Build a not-found exception with an optional message and previous exception.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
     */
    public function __construct(string $message = 'Not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $code, $previous);
    }
}

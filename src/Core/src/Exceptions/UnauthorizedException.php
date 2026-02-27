<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * UnauthorizedException defines a framework component for this package.
 */
class UnauthorizedException extends HttpException
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $message Input value.
     * @param int $code Input value.
     * @param \Throwable|null $previous Input value.
     * @return void Output value.
     */
    public function __construct(string $message = 'Unauthorized', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(401, $message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * ConflictException defines a framework component for this package.
 */
class ConflictException extends HttpException
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $message Input value.
     * @param int $code Input value.
     * @param \Throwable|null $previous Input value.
     * @return void Output value.
     */
    public function __construct(string $message = 'Conflict', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(409, $message, $code, $previous);
    }
}

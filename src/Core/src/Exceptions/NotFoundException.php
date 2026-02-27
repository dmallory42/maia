<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * NotFoundException defines a framework component for this package.
 */
class NotFoundException extends HttpException
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $message Input value.
     * @param int $code Input value.
     * @param \Throwable|null $previous Input value.
     * @return void Output value.
     */
    public function __construct(string $message = 'Not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

use RuntimeException;

/**
 * HttpException defines a framework component for this package.
 */
class HttpException extends RuntimeException
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param int $status Input value.
     * @param string $message Input value.
     * @param int $code Input value.
     * @param \Throwable|null $previous Input value.
     * @return void Output value.
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
     * Status and return int.
     * @return int Output value.
     */
    public function status(): int
    {
        return $this->status;
    }
}

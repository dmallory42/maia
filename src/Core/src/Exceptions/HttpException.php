<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message = 'HTTP error',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}

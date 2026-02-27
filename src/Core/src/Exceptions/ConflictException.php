<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(409, $message, $code, $previous);
    }
}

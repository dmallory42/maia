<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $code, $previous);
    }
}

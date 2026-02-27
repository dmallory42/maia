<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $code, $previous);
    }
}

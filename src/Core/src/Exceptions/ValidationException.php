<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

class ValidationException extends HttpException
{
    /** @param array<string, array<int, string>> $errors */
    public function __construct(private array $errors, string $message = 'Validation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(422, $message, $code, $previous);
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}

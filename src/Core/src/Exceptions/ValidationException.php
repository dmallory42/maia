<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * ValidationException defines a framework component for this package.
 */
class ValidationException extends HttpException
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param array $errors Input value.
     * @param string $message Input value.
     * @param int $code Input value.
     * @param \Throwable|null $previous Input value.
     * @return void Output value.
     */
    public function __construct(
        private array $errors,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(422, $message, $code, $previous);
    }

    /**
     * Errors and return array.
     * @return array Output value.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

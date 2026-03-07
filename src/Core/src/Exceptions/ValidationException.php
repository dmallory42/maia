<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

/**
 * HTTP 422 exception carrying field-level validation errors.
 */
class ValidationException extends HttpException
{
    /**
     * Build a validation exception with the collected field errors.
     * @param array $errors Validation errors keyed by field name.
     * @param string $message Error message exposed to the client.
     * @param int $code Internal exception code.
     * @param \Throwable|null $previous Previous exception in the chain.
     * @return void
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
     * Return the validation errors carried by the exception.
     * @return array Validation errors keyed by field name.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

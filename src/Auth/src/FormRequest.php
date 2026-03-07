<?php

declare(strict_types=1);

namespace Maia\Auth;

use Maia\Core\Exceptions\ValidationException;
use Maia\Core\Http\Request;

/**
 * Base class for validated request objects that automatically check the request body against defined rules.
 */
abstract class FormRequest extends Request
{
    /** @var array<string, mixed> */
    private array $validatedData = [];

    /**
     * Build the request and immediately validate its body against the subclass rules.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The request URI path.
     * @param array $query Query string parameters.
     * @param array $headers HTTP request headers.
     * @param string|null $body The raw request body (expected to be JSON).
     * @param array $routeParams Named route parameters extracted from the URL.
     * @param Validator|null $validator A custom Validator instance, or null to use the default.
     * @return void
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?string $body = null,
        array $routeParams = [],
        ?Validator $validator = null
    ) {
        parent::__construct($method, $path, $query, $headers, $body, $routeParams);

        $this->validateResolved($validator);
    }

    /**
     * Define the validation rules for this request.
     * @return array An associative array of field names to validation rule strings or arrays.
     */
    abstract protected function rules(): array;

    /**
     * Provide an optional uniqueness checker for `unique:{table}` rules used by this request.
     * @return callable|null Callback(table, field, value) => bool, or null when this request does not use unique rules.
     */
    protected function uniqueChecker(): ?callable
    {
        return null;
    }

    /**
     * Retrieve the validated request data (only fields defined in rules).
     * @return array The validated key-value pairs from the request body.
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Run validation against the request body and populate the validated data, or throw on failure.
     * @param Validator|null $validator The validator instance to use, or null to build one from uniqueChecker().
     * @return void
     */
    private function validateResolved(?Validator $validator): void
    {
        $payload = $this->body();
        $data = is_array($payload) ? $payload : [];

        $resolvedValidator = $validator ?? new Validator($this->uniqueChecker());
        $errors = $resolvedValidator->validate($data, $this->rules());
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->validatedData = [];
        foreach (array_keys($this->rules()) as $field) {
            if (array_key_exists($field, $data)) {
                $this->validatedData[$field] = $data[$field];
            }
        }
    }
}

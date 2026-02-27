<?php

declare(strict_types=1);

namespace Maia\Auth;

use Maia\Core\Exceptions\ValidationException;
use Maia\Core\Http\Request;

/**
 * FormRequest defines a framework component for this package.
 */
abstract class FormRequest extends Request
{
    /** @var array<string, mixed> */
    private array $validatedData = [];

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $method Input value.
     * @param string $path Input value.
     * @param array $query Input value.
     * @param array $headers Input value.
     * @param string|null $body Input value.
     * @param array $routeParams Input value.
     * @param Validator|null $validator Input value.
     * @return void Output value.
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

        $this->validateResolved($validator ?? new Validator());
    }

    /**
     * Rules and return array.
     * @return array Output value.
     */
    abstract protected function rules(): array;

    /**
     * Validated and return array.
     * @return array Output value.
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Validate resolved and return void.
     * @param Validator $validator Input value.
     * @return void Output value.
     */
    private function validateResolved(Validator $validator): void
    {
        $payload = $this->body();
        $data = is_array($payload) ? $payload : [];

        $errors = $validator->validate($data, $this->rules());
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

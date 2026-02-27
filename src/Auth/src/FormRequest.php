<?php

declare(strict_types=1);

namespace Maia\Auth;

use Maia\Core\Exceptions\ValidationException;
use Maia\Core\Http\Request;

abstract class FormRequest extends Request
{
    /** @var array<string, mixed> */
    private array $validatedData = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
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

    /** @return array<string, string|array<int, string>> */
    abstract protected function rules(): array;

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validatedData;
    }

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

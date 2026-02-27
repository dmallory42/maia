<?php

declare(strict_types=1);

namespace Maia\Auth;

class Validator
{
    /** @var callable(string, string, mixed): bool */
    private $uniqueChecker;

    public function __construct(?callable $uniqueChecker = null)
    {
        $this->uniqueChecker = $uniqueChecker ?? static fn (string $table, string $field, mixed $value): bool => true;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
     * @return array<string, array<int, string>>
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $parsedRules = $this->parseRules($ruleSet);

            foreach ($parsedRules as $rule) {
                $ruleName = $rule['name'];
                $argument = $rule['argument'];

                if ($ruleName !== 'required' && !$this->hasValue($value)) {
                    continue;
                }

                $message = match ($ruleName) {
                    'required' => $this->validateRequired($field, $value),
                    'string' => $this->validateString($field, $value),
                    'email' => $this->validateEmail($field, $value),
                    'integer' => $this->validateInteger($field, $value),
                    'boolean' => $this->validateBoolean($field, $value),
                    'min' => $this->validateMin($field, $value, $argument),
                    'max' => $this->validateMax($field, $value, $argument),
                    'unique' => $this->validateUnique($field, $value, $argument),
                    default => null,
                };

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * @param string|array<int, string> $ruleSet
     * @return array<int, array{name: string, argument: string|null}>
     */
    private function parseRules(string|array $ruleSet): array
    {
        $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);

        return array_map(static function (string $rule): array {
            $parts = explode(':', $rule, 2);

            return [
                'name' => strtolower(trim($parts[0])),
                'argument' => isset($parts[1]) ? trim($parts[1]) : null,
            ];
        }, $rules);
    }

    private function hasValue(mixed $value): bool
    {
        return !($value === null || $value === '');
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        return $this->hasValue($value) ? null : sprintf('The %s field is required.', $field);
    }

    private function validateString(string $field, mixed $value): ?string
    {
        return is_string($value) ? null : sprintf('The %s field must be a string.', $field);
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return sprintf('The %s field must be a valid email address.', $field);
        }

        return null;
    }

    private function validateInteger(string $field, mixed $value): ?string
    {
        if (is_int($value)) {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return null;
        }

        return sprintf('The %s field must be an integer.', $field);
    }

    private function validateBoolean(string $field, mixed $value): ?string
    {
        if (is_bool($value)) {
            return null;
        }

        return sprintf('The %s field must be a boolean.', $field);
    }

    private function validateMin(string $field, mixed $value, ?string $argument): ?string
    {
        if ($argument === null || !is_numeric($argument)) {
            return null;
        }

        $min = (float) $argument;

        if (is_string($value) && mb_strlen($value) < $min) {
            return sprintf('The %s field must be at least %s characters.', $field, $argument);
        }

        if ((is_int($value) || is_float($value)) && $value < $min) {
            return sprintf('The %s field must be at least %s.', $field, $argument);
        }

        return null;
    }

    private function validateMax(string $field, mixed $value, ?string $argument): ?string
    {
        if ($argument === null || !is_numeric($argument)) {
            return null;
        }

        $max = (float) $argument;

        if (is_string($value) && mb_strlen($value) > $max) {
            return sprintf('The %s field may not be greater than %s characters.', $field, $argument);
        }

        if ((is_int($value) || is_float($value)) && $value > $max) {
            return sprintf('The %s field may not be greater than %s.', $field, $argument);
        }

        return null;
    }

    private function validateUnique(string $field, mixed $value, ?string $argument): ?string
    {
        if ($argument === null) {
            return null;
        }

        $isUnique = (bool) ($this->uniqueChecker)($argument, $field, $value);

        return $isUnique ? null : sprintf('The %s has already been taken.', $field);
    }
}

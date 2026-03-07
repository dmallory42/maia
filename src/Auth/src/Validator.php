<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Rule-based data validator supporting required, type, length, and uniqueness checks.
 */
class Validator
{
    /** @var (callable(string, string, mixed): bool)|null */
    private $uniqueChecker;

    /** @var array<string, callable(string, mixed, ?string, array): ?string> */
    private array $customRules = [];

    /**
     * Initialize the validator with an optional callback for database uniqueness checks.
     * @param callable|null $uniqueChecker Callback(table, field, value) that returns true when the value is unique;
     *     defaults to an always-true no-op checker.
     * @return void
     */
    public function __construct(?callable $uniqueChecker = null)
    {
        $this->uniqueChecker = $uniqueChecker;
    }

    /**
     * Register a custom validation rule.
     * @param string $name Rule name used in validation strings.
     * @param callable $rule Callback(field, value, argument, data) => error message or null.
     * @return self The validator instance for chaining.
     */
    public function extend(string $name, callable $rule): self
    {
        $this->customRules[strtolower(trim($name))] = $rule;

        return $this;
    }

    /**
     * Validate the given data against a set of rules and return any errors.
     * @param array $data The key-value data to validate.
     * @param array $rules Validation rules keyed by field name (pipe-delimited string or array).
     * @return array An associative array of field => error messages; empty if validation passes.
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

                $message = $this->validateRule($ruleName, $field, $value, $argument, $data);

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * Parse a pipe-delimited rule string or array into structured rule definitions.
     * @param string|array $ruleSet The rules as a pipe-delimited string (e.g. 'required|string|min:3') or array.
     * @return array An array of ['name' => ..., 'argument' => ...] entries.
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

    /**
     * Validate one parsed rule against the field value.
     * @param string $ruleName Lowercased validation rule name.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @param string|null $argument Optional rule argument.
     * @param array $data Full payload under validation.
     * @return string|null Error message when invalid, otherwise null.
     */
    private function validateRule(
        string $ruleName,
        string $field,
        mixed $value,
        ?string $argument,
        array $data
    ): ?string {
        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'string' => $this->validateString($field, $value),
            'email' => $this->validateEmail($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'boolean' => $this->validateBoolean($field, $value),
            'min' => $this->validateMin($field, $value, $argument),
            'max' => $this->validateMax($field, $value, $argument),
            'unique' => $this->validateUnique($field, $value, $argument),
            default => $this->validateCustomRule($ruleName, $field, $value, $argument, $data),
        };
    }

    /**
     * Run a registered custom rule when one exists.
     * @param string $ruleName Lowercased custom rule name.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @param string|null $argument Optional rule argument.
     * @param array $data Full payload under validation.
     * @return string|null Error message when invalid, otherwise null.
     */
    private function validateCustomRule(
        string $ruleName,
        string $field,
        mixed $value,
        ?string $argument,
        array $data
    ): ?string {
        $rule = $this->customRules[$ruleName] ?? null;
        if ($rule === null) {
            return null;
        }

        return $rule($field, $value, $argument, $data);
    }

    /**
     * Check whether the value is present (not null and not an empty string).
     * @param mixed $value The value to check.
     * @return bool True if the value is non-null and non-empty.
     */
    private function hasValue(mixed $value): bool
    {
        return !($value === null || $value === '');
    }

    /**
     * Assert that the field has a non-empty value.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @return string|null An error message if the field is missing, or null if valid.
     */
    private function validateRequired(string $field, mixed $value): ?string
    {
        return $this->hasValue($value) ? null : sprintf('The %s field is required.', $field);
    }

    /**
     * Assert that the field value is a string.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @return string|null An error message if the value is not a string, or null if valid.
     */
    private function validateString(string $field, mixed $value): ?string
    {
        return is_string($value) ? null : sprintf('The %s field must be a string.', $field);
    }

    /**
     * Assert that the field value is a valid email address.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @return string|null An error message if the value is not a valid email, or null if valid.
     */
    private function validateEmail(string $field, mixed $value): ?string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return sprintf('The %s field must be a valid email address.', $field);
        }

        return null;
    }

    /**
     * Assert that the field value is an integer or a numeric string representing one.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @return string|null An error message if the value is not integer-like, or null if valid.
     */
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

    /**
     * Assert that the field value is a boolean.
     * @param string $field The field name being validated.
     * @param mixed $value The field value.
     * @return string|null An error message if the value is not a boolean, or null if valid.
     */
    private function validateBoolean(string $field, mixed $value): ?string
    {
        if (is_bool($value)) {
            return null;
        }

        return sprintf('The %s field must be a boolean.', $field);
    }

    /**
     * Assert that a string meets a minimum length or a number meets a minimum value.
     * @param string $field The field name being validated.
     * @param mixed $value The field value (string length or numeric value is checked).
     * @param string|null $argument The minimum threshold.
     * @return string|null An error message if the value is below the minimum, or null if valid.
     */
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

    /**
     * Assert that a string does not exceed a maximum length or a number does not exceed a maximum value.
     * @param string $field The field name being validated.
     * @param mixed $value The field value (string length or numeric value is checked).
     * @param string|null $argument The maximum threshold.
     * @return string|null An error message if the value exceeds the maximum, or null if valid.
     */
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

    /**
     * Assert that the field value is unique in the given database table.
     * @param string $field The field name being validated (used as the column to check).
     * @param mixed $value The value to check for uniqueness.
     * @param string|null $argument The database table name to check against.
     * @return string|null An error message if the value already exists, or null if unique.
     */
    private function validateUnique(string $field, mixed $value, ?string $argument): ?string
    {
        if ($argument === null) {
            return null;
        }

        if ($this->uniqueChecker === null) {
            return sprintf(
                'The %s field cannot use the unique rule without a configured unique checker.',
                $field
            );
        }

        $isUnique = (bool) ($this->uniqueChecker)($argument, $field, $value);

        return $isUnique ? null : sprintf('The %s has already been taken.', $field);
    }
}

<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testReturnsNoErrorsForValidPayload(): void
    {
        $validator = new Validator(fn (string $table, string $field, mixed $value): bool => true);

        $errors = $validator->validate(
            [
                'name' => 'Mal',
                'email' => 'mal@example.com',
                'age' => 34,
                'is_admin' => false,
            ],
            [
                'name' => 'required|string|min:2|max:50',
                'email' => 'required|email|unique:users',
                'age' => 'required|integer|min:18|max:99',
                'is_admin' => 'boolean',
            ]
        );

        $this->assertSame([], $errors);
    }

    public function testReturnsErrorsForInvalidPayload(): void
    {
        $validator = new Validator(fn (string $table, string $field, mixed $value): bool => false);

        $errors = $validator->validate(
            [
                'name' => '',
                'email' => 'bad-email',
                'age' => 'abc',
                'is_admin' => 'yes',
            ],
            [
                'name' => 'required|string|min:2',
                'email' => 'required|email|unique:users',
                'age' => 'required|integer',
                'is_admin' => 'boolean',
            ]
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('is_admin', $errors);
    }

    public function testUniqueRuleUsesChecker(): void
    {
        $calls = 0;
        $validator = new Validator(function (string $table, string $field, mixed $value) use (&$calls): bool {
            $calls++;

            return $value !== 'taken@example.com';
        });

        $errors = $validator->validate(
            ['email' => 'taken@example.com'],
            ['email' => 'unique:users']
        );

        $this->assertSame(1, $calls);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testUniqueRuleReturnsConfigurationErrorWithoutChecker(): void
    {
        $validator = new Validator();

        $errors = $validator->validate(
            ['email' => 'mal@example.com'],
            ['email' => 'unique:users']
        );

        $this->assertSame(
            ['email' => ['The email field cannot use the unique rule without a configured unique checker.']],
            $errors
        );
    }

    public function testCustomRuleCanBeRegisteredAndPass(): void
    {
        $validator = (new Validator())->extend(
            'starts_with',
            static function (string $field, mixed $value, ?string $argument): ?string {
                if (!is_string($value) || $argument === null || str_starts_with($value, $argument)) {
                    return null;
                }

                return sprintf('The %s field must start with %s.', $field, $argument);
            }
        );

        $errors = $validator->validate(
            ['username' => 'maia-bot'],
            ['username' => 'required|starts_with:maia']
        );

        $this->assertSame([], $errors);
    }

    public function testCustomRuleReturnsErrorMessageWhenValidationFails(): void
    {
        $validator = (new Validator())->extend(
            'starts_with',
            static function (string $field, mixed $value, ?string $argument, array $data): ?string {
                if (
                    is_string($value)
                    && $argument !== null
                    && str_starts_with($value, $argument)
                    && array_key_exists('username', $data)
                ) {
                    return null;
                }

                return sprintf('The %s field must start with %s.', $field, (string) $argument);
            }
        );

        $errors = $validator->validate(
            ['username' => 'bot-maia'],
            ['username' => 'starts_with:maia']
        );

        $this->assertSame(
            ['username' => ['The username field must start with maia.']],
            $errors
        );
    }
}

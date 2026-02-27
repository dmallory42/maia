<?php

declare(strict_types=1);

namespace Maia\Auth\Tests;

use Maia\Auth\FormRequest;
use Maia\Auth\Validator;
use Maia\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class CreateUserRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
        ];
    }
}

class FormRequestTest extends TestCase
{
    public function testValidatedReturnsSanitizedData(): void
    {
        $request = new CreateUserRequest(
            method: 'POST',
            path: '/users',
            query: [],
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"Mal","email":"mal@example.com","age":34}',
            routeParams: [],
            validator: new Validator()
        );

        $this->assertSame(
            [
                'name' => 'Mal',
                'email' => 'mal@example.com',
                'age' => 34,
            ],
            $request->validated()
        );
    }

    public function testThrowsValidationExceptionWhenInvalid(): void
    {
        $this->expectException(ValidationException::class);

        new CreateUserRequest(
            method: 'POST',
            path: '/users',
            query: [],
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"","email":"bad","age":10}',
            routeParams: [],
            validator: new Validator()
        );
    }
}

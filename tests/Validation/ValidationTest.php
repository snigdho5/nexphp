<?php

declare(strict_types=1);

namespace Nex\Tests\Validation;

use Nex\Http\ServerRequest;
use Nex\Validation\Exceptions\ValidationException;
use Nex\Validation\FormRequest;
use Nex\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testSuccessfulValidation(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'editor',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'extra_unwanted' => 'malicious_input',
        ];

        $rules = [
            'name' => 'required|string|min:3|max:50',
            'email' => 'required|email',
            'role' => 'required|in:admin,editor,user',
            'password' => 'required|min:8|confirmed',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->passes());
        $this->assertFalse($result->fails());
        $this->assertEmpty($result->errors());

        $validated = $result->validated();
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        // Untrusted input must not be present in validated output
        $this->assertArrayNotHasKey('extra_unwanted', $validated);
    }

    public function testValidationFailures(): void
    {
        $data = [
            'name' => 'J',
            'email' => 'invalid-email',
            'role' => 'superman',
            'password' => 'pass',
            'password_confirmation' => 'different',
        ];

        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'role' => 'required|in:admin,editor',
            'password' => 'required|min:8|confirmed',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->fails());
        $errors = $result->errors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('role', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testFormRequestValidation(): void
    {
        $request = (new ServerRequest('POST', '/api/users'))
            ->withParsedBody([
                'title' => 'Article One',
                'category' => 'tech',
            ]);

        $formRequest = new ArticleCreateRequest($request, $this->validator);
        $this->assertSame('Article One', $formRequest->validated()['title']);
        $this->assertSame('Article One', $formRequest->title);

        $invalidRequest = (new ServerRequest('POST', '/api/users'))
            ->withParsedBody(['title' => '']);

        $this->expectException(ValidationException::class);
        new ArticleCreateRequest($invalidRequest, $this->validator);
    }
}

class ArticleCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5',
            'category' => 'nullable|string',
        ];
    }
}

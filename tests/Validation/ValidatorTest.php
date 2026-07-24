<?php

namespace Trunk\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Trunk\Validation\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredFailsWhenFieldMissing(): void
    {
        $validator = Validator::make([], ['name' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertSame(['The name field is required.'], $validator->errors()['name']);
    }

    public function testRequiredFailsWhenFieldIsEmptyString(): void
    {
        $validator = Validator::make(['name' => ''], ['name' => 'required']);

        $this->assertTrue($validator->fails());
    }

    public function testPassesWhenRequiredFieldPresent(): void
    {
        $validator = Validator::make(['name' => 'Alice'], ['name' => 'required']);

        $this->assertFalse($validator->fails());
    }

    public function testNullableSkipsOtherRulesWhenFieldAbsent(): void
    {
        $validator = Validator::make([], ['nickname' => 'nullable|string|max:10']);

        $this->assertFalse($validator->fails());
    }

    public function testNullableStillValidatesWhenFieldPresent(): void
    {
        $validator = Validator::make(['age' => 'not-a-number'], ['age' => 'nullable|integer']);

        $this->assertTrue($validator->fails());
    }

    public function testStringRuleRejectsNonString(): void
    {
        $validator = Validator::make(['name' => 123], ['name' => 'string']);

        $this->assertTrue($validator->fails());
    }

    public function testIntegerRuleAcceptsNumericStringAndRejectsWords(): void
    {
        $validator = Validator::make(['age' => '42'], ['age' => 'integer']);
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['age' => 'forty-two'], ['age' => 'integer']);
        $this->assertTrue($validator->fails());
    }

    public function testEmailRuleValidatesFormat(): void
    {
        $validator = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['The email field must be a valid email address.'],
            $validator->errors()['email']
        );

        $validator = Validator::make(['email' => 'user@example.com'], ['email' => 'required|email']);
        $this->assertFalse($validator->fails());
    }

    public function testMinRuleUsesStringLengthForNonNumericValues(): void
    {
        $validator = Validator::make(['name' => 'ab'], ['name' => 'min:3']);

        $this->assertTrue($validator->fails());
    }

    public function testMinRuleUsesNumericValueForNumbers(): void
    {
        $validator = Validator::make(['age' => 5], ['age' => 'min:18']);

        $this->assertTrue($validator->fails());
    }

    public function testMaxRuleRejectsValuesAboveLimit(): void
    {
        $validator = Validator::make(['name' => 'this name is far too long'], ['name' => 'max:5']);

        $this->assertTrue($validator->fails());
    }

    public function testInRuleRestrictsToAllowedValues(): void
    {
        $validator = Validator::make(['role' => 'admin'], ['role' => 'in:user,admin']);
        $this->assertFalse($validator->fails());

        $validator = Validator::make(['role' => 'root'], ['role' => 'in:user,admin']);
        $this->assertTrue($validator->fails());
    }

    public function testValidatedReturnsOnlyDeclaredFields(): void
    {
        $validator = Validator::make(
            ['name' => 'Alice', 'email' => 'alice@example.com', 'extra' => 'ignored'],
            ['name' => 'required', 'email' => 'required|email']
        );

        $this->assertSame(
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            $validator->validated()
        );
    }

    public function testMultipleFailuresAreCollectedPerField(): void
    {
        $validator = Validator::make([], [
            'name' => 'required',
            'email' => 'required|email',
        ]);

        $errors = $validator->errors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }
}

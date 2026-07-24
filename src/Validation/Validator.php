<?php

namespace Trunk\Validation;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

class Validator
{
    private array $errors = [];
    private bool $ran = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules Field name => pipe-delimited rule string, e.g. 'required|string|max:255'.
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {}

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        $this->run();
        return !empty($this->errors);
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        $this->run();
        return $this->errors;
    }

    /**
     * Returns only the fields that were declared in the rule set.
     */
    public function validated(): array
    {
        $this->run();
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $isPresent = array_key_exists($field, $this->data) && $value !== null && $value !== '';
            $rules = explode('|', $ruleString);

            if (in_array('nullable', $rules, true) && !$isPresent) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = str_contains($rule, ':') ? explode(':', $rule, 2) : [$rule, null];

                if ($name === 'required' && !$isPresent) {
                    $this->addError($field, "The {$field} field is required.");
                    continue 2;
                }

                if (!$isPresent) {
                    continue;
                }

                $this->applyRule($field, $value, $name, $parameter);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $parameter): void
    {
        match ($rule) {
            'string' => is_string($value) ?: $this->addError($field, "The {$field} field must be a string."),
            'integer', 'int' => (is_int($value) || is_string($value) && ctype_digit($value))
                ?: $this->addError($field, "The {$field} field must be an integer."),
            'numeric' => is_numeric($value) ?: $this->addError($field, "The {$field} field must be numeric."),
            'boolean', 'bool' => in_array($value, [true, false, 0, 1, '0', '1'], true)
                ?: $this->addError($field, "The {$field} field must be a boolean."),
            'array' => is_array($value) ?: $this->addError($field, "The {$field} field must be an array."),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ?: $this->addError($field, "The {$field} field must be a valid email address."),
            'min' => $this->checkMin($field, $value, (float) $parameter),
            'max' => $this->checkMax($field, $value, (float) $parameter),
            'in' => in_array((string) $value, explode(',', (string) $parameter), true)
                ?: $this->addError($field, "The selected {$field} is invalid."),
            default => null,
        };
    }

    private function checkMin(string $field, mixed $value, float $min): void
    {
        $size = is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
        if ($size < $min) {
            $this->addError($field, "The {$field} field must be at least {$min}.");
        }
    }

    private function checkMax(string $field, mixed $value, float $max): void
    {
        $size = is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
        if ($size > $max) {
            $this->addError($field, "The {$field} field must not be greater than {$max}.");
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}

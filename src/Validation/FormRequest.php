<?php

namespace Trunk\Validation;

use Psr\Http\Message\ServerRequestInterface;
use Trunk\Validation\Exception\ValidationException;

abstract class FormRequest
{
    private array $data;
    private array $validated = [];

    public function __construct(private readonly ServerRequestInterface $request)
    {
        $this->data = [
            ...$request->getAttributes(),
            ...$request->getQueryParams(),
            ...(array) ($request->getParsedBody() ?? [])
        ];
    }

    /**
     * @return array<string, string> Field name => pipe-delimited rule string.
     */
    abstract public function rules(): array;

    /**
     * @throws ValidationException if the request data fails validation.
     */
    public function validate(): void
    {
        $validator = Validator::make($this->data, $this->rules());

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        $this->validated = $validator->validated();
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getServerRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}

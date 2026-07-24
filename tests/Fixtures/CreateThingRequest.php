<?php

namespace Trunk\Tests\Fixtures;

use Trunk\Validation\FormRequest;

class CreateThingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }
}

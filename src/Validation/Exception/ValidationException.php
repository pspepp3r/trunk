<?php

namespace Trunk\Validation\Exception;

class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, string[]> $errors Field name => list of failure messages.
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }
}

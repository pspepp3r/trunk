<?php

namespace Trunk\Tests\Fixtures;

class UnresolvableService
{
    public function __construct(public readonly string $requiredButUntyped)
    {
    }
}

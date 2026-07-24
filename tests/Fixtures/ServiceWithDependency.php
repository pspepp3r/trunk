<?php

namespace Trunk\Tests\Fixtures;

class ServiceWithDependency
{
    public function __construct(public readonly PlainService $plainService)
    {
    }
}

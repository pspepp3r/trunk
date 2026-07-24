<?php

namespace Trunk\Tests\Fixtures;

class ServiceWithScalarDefault
{
    public function __construct(public readonly int $limit = 10)
    {
    }
}

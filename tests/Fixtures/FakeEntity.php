<?php

namespace Trunk\Tests\Fixtures;

use Trunk\ORM\Interface\EntityInterface;

class FakeEntity implements EntityInterface
{
    public function __construct(public readonly int $id)
    {
    }
}

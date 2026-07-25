<?php

namespace Trunk\Tests\Fixtures;

use Trunk\ORM\BaseEntity;

class FakeEntity extends BaseEntity
{
    public function __construct(public readonly int $id) {}
}

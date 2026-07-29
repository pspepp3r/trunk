<?php

namespace Trunk\Database\ORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public ?string $table = null
    ) {}
}

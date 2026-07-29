<?php

namespace Trunk\Database\ORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public ?string $targetEntity = null,
        public ?string $inversedBy = null
    ) {}
}

<?php

namespace Trunk\Database\ORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(
        public ?string $targetEntity = null,
        public ?string $mappedBy = null,
        public ?string $inversedBy = null
    ) {}
}

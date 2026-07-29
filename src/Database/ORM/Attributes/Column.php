<?php

namespace Trunk\Database\ORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public ?string $type = null,
        public ?string $name = null,
        public ?int $length = null,
        public bool $nullable = false,
        public bool $primary = false,
        public bool $autoIncrement = false
    ) {}
}

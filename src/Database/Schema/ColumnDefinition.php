<?php

namespace Trunk\Database\Schema;

class ColumnDefinition
{
    public bool $nullable = false;
    public bool $unique = false;
    public bool $hasDefault = false;
    public mixed $default = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $params = [],
    ) {
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function unique(bool $value = true): static
    {
        $this->unique = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }
}

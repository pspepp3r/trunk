<?php

declare(strict_types=1);

namespace Trunk\ORM;

use ArrayAccess;
use JsonSerializable;
use ReturnTypeWillChange;
use function array_key_exists;

abstract class BaseEntity implements ArrayAccess, JsonSerializable
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize());
    }

    #[ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    #[ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (property_exists($this, $offset)) {
            $this->{$offset} = $value;
        }
    }

    #[ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        if (property_exists($this, $offset)) {
            $this->{$offset} = null;
        }
    }
}

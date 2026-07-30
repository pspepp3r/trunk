<?php

namespace Trunk\Database\Schema\Diff;

class TableSchema
{
    /**
     * @param ColumnSchema[] $columns Keyed by column name.
     * @param ForeignKeySchema[] $foreignKeys Keyed by local column name.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns = [],
        public readonly array $foreignKeys = [],
    ) {
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function hasForeignKey(string $column): bool
    {
        return isset($this->foreignKeys[$column]);
    }
}

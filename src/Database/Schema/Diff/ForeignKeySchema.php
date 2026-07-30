<?php

namespace Trunk\Database\Schema\Diff;

class ForeignKeySchema
{
    public function __construct(
        public readonly string $column,
        public readonly string $referencesTable,
        public readonly string $referencesColumn = 'id',
        public readonly string $onDelete = 'CASCADE',
    ) {
    }

    public function constraintName(string $table): string
    {
        return "fk_{$table}_{$this->column}";
    }
}

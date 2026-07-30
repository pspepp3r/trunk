<?php

namespace Trunk\Database\Schema\Diff;

/**
 * Semantic description of a single column, used by both SchemaReader (expected,
 * from #[Entity]/#[Column] attributes) and SchemaIntrospector (actual, from the
 * live database) so SchemaComparator can diff the two on equal footing.
 */
class ColumnSchema
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly bool $nullable = false,
        public readonly bool $primary = false,
        public readonly bool $autoIncrement = false,
    ) {
    }
}

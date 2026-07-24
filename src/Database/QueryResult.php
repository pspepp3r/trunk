<?php

namespace Trunk\Database;

class QueryResult
{
    public function __construct(
        public readonly array $rows = [],
        public readonly int|string|null $insertId = null,
        public readonly int $affectedRows = 0,
    ) {}
}

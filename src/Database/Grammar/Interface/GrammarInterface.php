<?php

namespace Trunk\Database\Grammar\Interface;

use Trunk\Database\QueryResult;

interface GrammarInterface
{
    public function quoteIdentifier(string $name): string;

    public function columnType(string $phpType): string;

    /**
     * Maps a semantic Blueprint column type (string, integer, boolean, text, ...)
     * to a dialect-specific SQL type, used by the migrations Schema DSL.
     */
    public function columnSql(string $type, array $params = []): string;

    public function primaryKeyColumn(string $name): string;

    public function compileBindings(string $sql): string;

    public function insertReturningClause(string $primaryKey): string;

    public function extractInsertId(QueryResult $result, string $primaryKey): int|string|null;

    public function tableOptions(): string;
}

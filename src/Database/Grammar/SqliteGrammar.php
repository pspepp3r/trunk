<?php

namespace Trunk\Database\Grammar;

use Trunk\Database\Grammar\Interface\GrammarInterface;

class SqliteGrammar implements GrammarInterface
{
    public function compileBindings(string $sql): string
    {
        return $sql;
    }

    public function primaryKeyColumn(string $name): string
    {
        return sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT', $this->quoteIdentifier($name));
    }

    public function columnType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'INTEGER',
            'float' => 'REAL',
            'bool' => 'INTEGER',
            'string' => 'TEXT',
            'array' => 'TEXT',
            default => 'TEXT',
        };
    }

    public function columnSql(string $type, array $params = []): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . ($params['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer', 'bigInteger', 'boolean' => 'INTEGER',
            'float' => 'REAL',
            'dateTime', 'timestamp' => 'DATETIME',
            'json' => 'TEXT',
            default => 'TEXT',
        };
    }

    public function insertReturningClause(string $primaryKey): string
    {
        return '';
    }

    public function extractInsertId(\Trunk\Database\QueryResult $result, string $primaryKey): int|string|null
    {
        return $result->insertId;
    }

    public function tableOptions(): string
    {
        return '';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

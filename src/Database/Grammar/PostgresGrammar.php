<?php

namespace Trunk\Database\Grammar;

use Trunk\Database\Grammar\Interface\GrammarInterface;
use Trunk\Database\QueryResult;

class PostgresGrammar implements GrammarInterface
{
    public function quoteIdentifier(string $name): string
    {
        return "\"{$name}\"";
    }

    public function columnType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'INTEGER',
            'bool' => 'BOOLEAN',
            'float' => 'DOUBLE PRECISION',
            default => 'VARCHAR(255)',
        };
    }

    public function columnSql(string $type, array $params = []): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . ($params['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigInteger' => 'BIGINT',
            'boolean' => 'BOOLEAN',
            'float' => 'DOUBLE PRECISION',
            'dateTime', 'timestamp' => 'TIMESTAMP',
            'json' => 'JSONB',
            default => 'VARCHAR(255)',
        };
    }

    public function primaryKeyColumn(string $name): string
    {
        return "\"{$name}\" SERIAL PRIMARY KEY";
    }

    public function compileBindings(string $sql): string
    {
        $index = 0;
        return preg_replace_callback('/\?/', function () use (&$index) {
            $index++;
            return "\${$index}";
        }, $sql);
    }

    public function insertReturningClause(string $primaryKey): string
    {
        return " RETURNING \"{$primaryKey}\"";
    }

    public function extractInsertId(QueryResult $result, string $primaryKey): int|string|null
    {
        return $result->rows[0][$primaryKey] ?? null;
    }

    public function tableOptions(): string
    {
        return '';
    }
}

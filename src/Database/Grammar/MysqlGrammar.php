<?php

namespace Trunk\Database\Grammar;

use Trunk\Database\Grammar\Interface\GrammarInterface;
use Trunk\Database\QueryResult;

class MysqlGrammar implements GrammarInterface
{
    public function quoteIdentifier(string $name): string
    {
        return "`{$name}`";
    }

    public function columnType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'INT',
            'bool' => 'TINYINT(1)',
            'float' => 'DOUBLE',
            default => 'VARCHAR(255)',
        };
    }

    public function columnSql(string $type, array $params = []): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . ($params['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INT',
            'bigInteger' => 'BIGINT',
            'boolean' => 'TINYINT(1)',
            'float' => 'DOUBLE',
            'dateTime', 'timestamp' => 'DATETIME',
            'json' => 'JSON',
            default => 'VARCHAR(255)',
        };
    }

    public function primaryKeyColumn(string $name): string
    {
        return "`{$name}` INT AUTO_INCREMENT PRIMARY KEY";
    }

    public function compileBindings(string $sql): string
    {
        return $sql;
    }

    public function insertReturningClause(string $primaryKey): string
    {
        return '';
    }

    public function extractInsertId(QueryResult $result, string $primaryKey): int|string|null
    {
        return $result->insertId;
    }

    public function tableOptions(): string
    {
        return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }
}

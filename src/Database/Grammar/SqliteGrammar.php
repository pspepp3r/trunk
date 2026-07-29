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

    public function tableOptions(): string
    {
        return '';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

<?php

namespace Trunk\Database\Schema;

use Trunk\Database\Grammar\Interface\GrammarInterface;

use function is_bool;
use function is_string;
use function sprintf;

class Blueprint
{
    /** @var ColumnDefinition[] */
    private array $columns = [];

    public function __construct(
        private readonly string $table,
        private readonly GrammarInterface $grammar,
    ) {}

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn($name, 'id');
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'float');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'dateTime');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function toCreateSql(): string
    {
        $definitions = array_map($this->compileColumn(...), $this->columns);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s;",
            $this->grammar->quoteIdentifier($this->table),
            implode(",\n  ", $definitions),
            $this->grammar->tableOptions()
        );
    }

    /**
     * @return string[]
     */
    public function toAlterSql(): array
    {
        return array_map(
            fn(ColumnDefinition $c) => sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $this->grammar->quoteIdentifier($this->table),
                $this->compileColumn($c)
            ),
            $this->columns
        );
    }

    private function addColumn(string $name, string $type, array $params = []): ColumnDefinition
    {
        $column = new ColumnDefinition($name, $type, $params);
        $this->columns[] = $column;
        return $column;
    }

    private function compileColumn(ColumnDefinition $column): string
    {
        if ($column->type === 'id') {
            return $this->grammar->primaryKeyColumn($column->name);
        }

        $sql = $this->grammar->quoteIdentifier($column->name) . ' ' . $this->grammar->columnSql($column->type, $column->params);
        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->compileDefault($column->default);
        }

        return $sql;
    }

    private function compileDefault(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_string($value) => "'" . str_replace("'", "''", $value) . "'",
            default => (string) $value,
        };
    }
}

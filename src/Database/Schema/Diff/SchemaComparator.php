<?php

namespace Trunk\Database\Schema\Diff;

use Trunk\Database\Grammar\Interface\GrammarInterface;
use Trunk\Database\Grammar\PostgresGrammar;

/** Additive-only schema comparator - see the Database guide's orm:schema-diff section. */
class SchemaComparator
{
    private const TYPE_MAP = [
        'VARCHAR' => 'string',
        'TEXT' => 'text',
        'INT' => 'integer',
        'INTEGER' => 'integer',
        'BIGINT' => 'bigInteger',
        'BOOLEAN' => 'boolean',
        'BOOL' => 'boolean',
        'FLOAT' => 'float',
        'DOUBLE' => 'float',
        'DATETIME' => 'dateTime',
        'TIMESTAMP' => 'timestamp',
        'JSON' => 'json',
    ];

    public function __construct(private readonly GrammarInterface $grammar)
    {
    }

    /**
     * @param array<string, TableSchema> $expected
     * @param array<string, TableSchema> $actual
     * @return array{up: string[], down: string[]}
     */
    public function diff(array $expected, array $actual): array
    {
        $up = [];
        $down = [];

        foreach ($expected as $tableName => $table) {
            if (!isset($actual[$tableName])) {
                $up[] = $this->compileCreateTable($table);
                $down[] = $this->compileDropTable($tableName);
            }
        }

        foreach ($expected as $tableName => $table) {
            $actualTable = $actual[$tableName] ?? null;
            if ($actualTable === null) {
                continue;
            }

            foreach ($table->columns as $column) {
                if (!$actualTable->hasColumn($column->name)) {
                    $up[] = $this->compileAddColumn($tableName, $column);
                    $down[] = $this->compileDropColumn($tableName, $column->name);
                }
            }
        }

        foreach ($expected as $tableName => $table) {
            $actualTable = $actual[$tableName] ?? null;

            foreach ($table->foreignKeys as $fk) {
                if ($actualTable !== null && $actualTable->hasForeignKey($fk->column)) {
                    continue;
                }

                $up[] = $this->compileAddForeignKey($tableName, $fk);
                $down[] = $this->compileDropForeignKey($tableName, $fk);
            }
        }

        return ['up' => $up, 'down' => array_reverse($down)];
    }

    private function compileCreateTable(TableSchema $table): string
    {
        $columnLines = array_map(
            fn(ColumnSchema $column) => $this->compileColumnDefinition($column),
            array_values($table->columns)
        );

        return sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s;",
            $this->grammar->quoteIdentifier($table->name),
            implode(",\n  ", $columnLines),
            $this->grammar->tableOptions()
        );
    }

    private function compileColumnDefinition(ColumnSchema $column): string
    {
        if ($column->primary) {
            return $this->grammar->primaryKeyColumn($column->name);
        }

        $semanticType = self::TYPE_MAP[strtoupper($column->type)] ?? 'string';
        $params = $semanticType === 'string' ? ['length' => $column->length ?? 255] : [];

        $sql = $this->grammar->quoteIdentifier($column->name)
            . ' ' . $this->grammar->columnSql($semanticType, $params);
        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        return $sql;
    }

    private function compileAddColumn(string $tableName, ColumnSchema $column): string
    {
        return sprintf(
            'ALTER TABLE %s ADD COLUMN %s',
            $this->grammar->quoteIdentifier($tableName),
            $this->compileColumnDefinition($column)
        );
    }

    private function compileDropColumn(string $tableName, string $columnName): string
    {
        return sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->grammar->quoteIdentifier($tableName),
            $this->grammar->quoteIdentifier($columnName)
        );
    }

    private function compileDropTable(string $tableName): string
    {
        return sprintf('DROP TABLE IF EXISTS %s', $this->grammar->quoteIdentifier($tableName));
    }

    private function compileAddForeignKey(string $tableName, ForeignKeySchema $fk): string
    {
        return sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s',
            $this->grammar->quoteIdentifier($tableName),
            $this->grammar->quoteIdentifier($fk->constraintName($tableName)),
            $this->grammar->quoteIdentifier($fk->column),
            $this->grammar->quoteIdentifier($fk->referencesTable),
            $this->grammar->quoteIdentifier($fk->referencesColumn),
            $fk->onDelete
        );
    }

    private function compileDropForeignKey(string $tableName, ForeignKeySchema $fk): string
    {
        $clause = $this->grammar instanceof PostgresGrammar ? 'CONSTRAINT' : 'FOREIGN KEY';

        return sprintf(
            'ALTER TABLE %s DROP %s %s',
            $this->grammar->quoteIdentifier($tableName),
            $clause,
            $this->grammar->quoteIdentifier($fk->constraintName($tableName))
        );
    }
}

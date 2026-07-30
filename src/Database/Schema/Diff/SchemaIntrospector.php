<?php

namespace Trunk\Database\Schema\Diff;

use React\Promise\PromiseInterface;
use Trunk\Database\Connection;
use Trunk\Database\Exception\UnsupportedDriverException;

/** Reads the live database schema via information_schema (MySQL/Postgres only) - see the Database guide's orm:schema-diff section. */
class SchemaIntrospector
{
    public function __construct(private readonly Connection $connection)
    {
        if (!in_array($connection->driverName(), ['mysql', 'pgsql'], true)) {
            throw new UnsupportedDriverException(
                "Schema introspection only supports 'mysql' and 'pgsql', not '{$connection->driverName()}'."
            );
        }
    }

    /**
     * @return PromiseInterface<array<string, TableSchema>> Keyed by table name.
     */
    public function introspect(): PromiseInterface
    {
        return $this->tableNames()->then(function (array $tableNames) {
            if (empty($tableNames)) {
                return [];
            }

            return $this->columnsByTable()->then(
                fn(array $columnsByTable) => $this->foreignKeysByTable()->then(
                    function (array $fksByTable) use ($tableNames, $columnsByTable) {
                        $tables = [];
                        foreach ($tableNames as $name) {
                            $tables[$name] = new TableSchema(
                                $name,
                                $columnsByTable[$name] ?? [],
                                $fksByTable[$name] ?? []
                            );
                        }

                        return $tables;
                    }
                )
            );
        });
    }

    private function isPostgres(): bool
    {
        return $this->connection->driverName() === 'pgsql';
    }

    private function tableNames(): PromiseInterface
    {
        $sql = $this->isPostgres()
            ? "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'"
            : 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()';

        return $this->connection->query($sql)->then(
            fn($result) => array_column($result->rows, 'table_name')
        );
    }

    /**
     * @return PromiseInterface<array<string, array<string, ColumnSchema>>>
     */
    private function columnsByTable(): PromiseInterface
    {
        $sql = $this->isPostgres()
            ? 'SELECT table_name, column_name, is_nullable FROM information_schema.columns WHERE table_schema = current_schema()'
            : 'SELECT table_name, column_name, is_nullable FROM information_schema.columns WHERE table_schema = DATABASE()';

        return $this->connection->query($sql)->then(function ($result) {
            $byTable = [];
            foreach ($result->rows as $row) {
                $table = $row['table_name'];
                $column = $row['column_name'];
                $byTable[$table][$column] = new ColumnSchema(
                    name: $column,
                    type: 'UNKNOWN',
                    nullable: strtoupper((string) $row['is_nullable']) === 'YES',
                );
            }

            return $byTable;
        });
    }

    /**
     * @return PromiseInterface<array<string, array<string, ForeignKeySchema>>>
     */
    private function foreignKeysByTable(): PromiseInterface
    {
        if ($this->isPostgres()) {
            $sql = "
                SELECT
                    tc.table_name AS table_name,
                    kcu.column_name AS column_name,
                    ccu.table_name AS referenced_table_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage ccu
                    ON tc.constraint_name = ccu.constraint_name AND tc.table_schema = ccu.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema()
            ";
        } else {
            $sql = '
                SELECT table_name, column_name, referenced_table_name
                FROM information_schema.key_column_usage
                WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL
            ';
        }

        return $this->connection->query($sql)->then(function ($result) {
            $byTable = [];
            foreach ($result->rows as $row) {
                $table = $row['table_name'];
                $column = $row['column_name'];
                $byTable[$table][$column] = new ForeignKeySchema($column, $row['referenced_table_name']);
            }

            return $byTable;
        });
    }
}

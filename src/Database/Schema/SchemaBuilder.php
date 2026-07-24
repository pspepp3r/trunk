<?php

namespace Trunk\Database\Schema;

use React\Promise\PromiseInterface;
use Trunk\Database\Connection;

use function React\Promise\resolve;
use function sprintf;

class SchemaBuilder
{
    public function __construct(private readonly Connection $db) {}

    public function create(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table, $this->db->grammar());
        $callback($blueprint);

        return $this->db->query($blueprint->toCreateSql());
    }

    public function table(string $table, callable $callback): PromiseInterface
    {
        $blueprint = new Blueprint($table, $this->db->grammar());
        $callback($blueprint);

        $promise = resolve(null);
        foreach ($blueprint->toAlterSql() as $sql) {
            $promise = $promise->then(fn() => $this->db->query($sql));
        }

        return $promise;
    }

    public function drop(string $table): PromiseInterface
    {
        return $this->db->query(sprintf(
            'DROP TABLE IF EXISTS %s',
            $this->db->grammar()->quoteIdentifier($table)
        ));
    }
}

<?php

namespace Trunk\Database\Driver;

use Clue\React\SQLite\DatabaseInterface;
use Clue\React\SQLite\Factory;
use Clue\React\SQLite\Result;
use React\Promise\PromiseInterface;
use Trunk\Database\Driver\Interface\DriverInterface;
use Trunk\Database\QueryResult;

class SqliteDriver implements DriverInterface
{
    private DatabaseInterface $db;

    public function __construct(array $config)
    {
        $factory = new Factory();
        $database = $config['database'] ?? ':memory:';
        
        $this->db = $factory->openLazy($database);
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        return $this->db->query($sql, $params)->then(function (Result $result) {
            return new QueryResult(
                rows: $result->rows ?? [],
                insertId: $result->insertId ?: null,
                affectedRows: $result->changed ?? 0,
            );
        });
    }
}

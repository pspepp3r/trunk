<?php

namespace Trunk\Database;

use React\Promise\PromiseInterface;
use Trunk\Config\Repository;
use Trunk\Database\Driver\Interface\DriverInterface;
use Trunk\Database\Driver\MysqlDriver;
use Trunk\Database\Driver\PostgresDriver;
use Trunk\Database\Exception\UnsupportedDriverException;
use Trunk\Database\Grammar\Interface\GrammarInterface;
use Trunk\Database\Grammar\MysqlGrammar;
use Trunk\Database\Grammar\PostgresGrammar;

class Connection
{
    private DriverInterface $driver;
    private GrammarInterface $grammar;

    public function __construct(Repository $config)
    {
        $driverName = $config->get('database.default', 'mysql');
        $dbConfig = $config->get("database.connections.{$driverName}");

        if (!$dbConfig) {
            throw new \Exception("Database configuration for driver '{$driverName}' is missing.");
        }

        [$this->driver, $this->grammar] = match ($driverName) {
            'mysql' => [new MysqlDriver($dbConfig), new MysqlGrammar()],
            'pgsql' => [new PostgresDriver($dbConfig), new PostgresGrammar()],
            'mongodb' => throw new UnsupportedDriverException(
                "The 'mongodb' driver is not yet supported by Trunk's database layer. There is no maintained non-blocking MongoDB client for ReactPHP; wiring one up today would mean silently blocking the event loop on every query."
            ),
            default => throw new \Exception("Unsupported database driver '{$driverName}'."),
        };
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        return $this->driver->query($this->grammar->compileBindings($sql), $params);
    }

    public function grammar(): GrammarInterface
    {
        return $this->grammar;
    }
}

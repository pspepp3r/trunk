<?php

namespace Trunk\Database\Driver;

use PgAsync\Client;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Trunk\Database\Driver\Interface\DriverInterface;
use Trunk\Database\QueryResult;

class PostgresDriver implements DriverInterface
{
    private Client $client;

    public function __construct(array $config)
    {
        $this->client = new Client([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? 5432),
            'user' => $config['username'] ?? 'postgres',
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? '',
        ]);
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        $deferred = new Deferred();
        $rows = [];

        $observable = empty($params)
            ? $this->client->query($sql)
            : $this->client->executeStatement($sql, $params);

        $observable->subscribe(
            function ($row) use (&$rows) {
                $rows[] = (array) $row;
            },
            function (\Throwable $e) use ($deferred) {
                $deferred->reject($e);
            },
            function () use ($deferred, &$rows) {
                $deferred->resolve(new QueryResult(
                    rows: $rows,
                    insertId: null,
                    affectedRows: count($rows),
                ));
            }
        );

        return $deferred->promise();
    }
}

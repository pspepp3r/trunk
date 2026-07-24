<?php

namespace Trunk\Database\Driver;

use React\Mysql\MysqlClient;
use React\Promise\PromiseInterface;
use Trunk\Database\Driver\Interface\DriverInterface;
use Trunk\Database\QueryResult;
use function sprintf;

class MysqlDriver implements DriverInterface
{
    private MysqlClient $client;

    public function __construct(array $config)
    {
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';

        $uri = sprintf(
            '%s:%s@%s:%d/%s',
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port,
            $database
        );

        $this->client = new MysqlClient($uri);
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        return $this->client->query($sql, $params)->then(fn($result) => new QueryResult(
            rows: $result->resultRows ?? [],
            insertId: $result->insertId ?: null,
            affectedRows: $result->affectedRows ?? 0,
        ));
    }
}

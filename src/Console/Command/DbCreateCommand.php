<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Trunk\Config\Repository;
use Trunk\Database\Driver\MysqlDriver;
use Trunk\Database\Driver\PostgresDriver;
use Trunk\Database\Exception\UnsupportedDriverException;

class DbCreateCommand extends Command
{
    public static function description(): string
    {
        return 'Create the configured database if it does not already exist';
    }

    public function handle(array $args): void
    {
        $config = $this->app->getContainer()->get(Repository::class);
        $driverName = $config->get('database.default', 'mysql');
        $dbConfig = $config->get("database.connections.{$driverName}");

        if (!$dbConfig) {
            echo "No configuration found for driver '{$driverName}'.\n";
            return;
        }

        $databaseName = $dbConfig['database'] ?? null;

        if (!$databaseName) {
            echo "No database name configured under database.connections.{$driverName}.database.\n";
            return;
        }

        try {
            $promise = match ($driverName) {
                'mysql' => $this->createMysqlDatabase($dbConfig, $databaseName),
                'pgsql' => $this->createPostgresDatabase($dbConfig, $databaseName),
                default => throw new UnsupportedDriverException(
                    "db:create does not support the '{$driverName}' driver."
                ),
            };
        } catch (UnsupportedDriverException $e) {
            echo $e->getMessage() . "\n";
            return;
        }

        $promise->then(
            function () use ($databaseName) {
                echo "Database '{$databaseName}' is ready.\n";
                Loop::get()->stop();
            },
            function (\Throwable $e) {
                echo 'Failed to create database: ' . $e->getMessage() . "\n";
                Loop::get()->stop();
            }
        );

        Loop::get()->run();
    }

    /**
     * Connects without selecting a database (MySQL allows this) and creates it if missing.
     */
    private function createMysqlDatabase(array $dbConfig, string $databaseName): PromiseInterface
    {
        $adminConfig = $dbConfig;
        $adminConfig['database'] = '';

        $driver = new MysqlDriver($adminConfig);

        return $driver->query("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
    }

    /**
     * Postgres has no CREATE DATABASE IF NOT EXISTS, and every connection must select a
     * database - so this connects to the "postgres" maintenance database (present on every
     * install), checks pg_database, and only issues CREATE DATABASE if it's missing.
     */
    private function createPostgresDatabase(array $dbConfig, string $databaseName): PromiseInterface
    {
        $adminConfig = $dbConfig;
        $adminConfig['database'] = 'postgres';

        $driver = new PostgresDriver($adminConfig);

        return $driver->query('SELECT 1 FROM pg_database WHERE datname = $1', [$databaseName])
            ->then(function ($result) use ($driver, $databaseName) {
                if (!empty($result->rows)) {
                    return $result;
                }

                return $driver->query("CREATE DATABASE \"{$databaseName}\"");
            });
    }
}

<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;
use ReflectionClass;
use ReflectionNamedType;
use Trunk\Database\Connection;

class SchemaSyncCommand extends Command
{
    public static function description(): string
    {
        return 'Analyze entities and synchronize database tables (quick prototyping)';
    }

    public function handle(array $args): void
    {
        $this->app->bootProviders();

        $basePath = $this->app->getBasePath();
        $entitiesDir = "{$basePath}/src/Entities";

        if (!is_dir($entitiesDir)) {
            echo "No Entities folder found. Nothing to sync.\n";
            return;
        }

        $db = $this->app->getContainer()->get(Connection::class);
        $grammar = $db->grammar();

        $entityFiles = scandir($entitiesDir);
        $queries = [];

        foreach ($entityFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $className = 'App\\Entities\\' . pathinfo($file, PATHINFO_FILENAME);
            if (!class_exists($className)) {
                require_once "{$entitiesDir}/$file";
            }

            $reflector = new ReflectionClass($className);
            $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflector->getShortName())) . 's';

            $columns = [];
            foreach ($reflector->getProperties() as $property) {
                $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()));

                if ($name === 'id') {
                    $columns[] = $grammar->primaryKeyColumn($name);
                    continue;
                }

                $type = 'VARCHAR(255)';
                if ($property->hasType()) {
                    $propType = $property->getType();
                    if ($propType instanceof ReflectionNamedType) {
                        $type = $grammar->columnType($propType->getName());
                    }
                }

                $columns[] = sprintf('%s %s NULL', $grammar->quoteIdentifier($name), $type);
            }

            $sql = sprintf(
                "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s;",
                $grammar->quoteIdentifier($tableName),
                implode(",\n  ", $columns),
                $grammar->tableOptions()
            );

            $queries[$tableName] = $sql;
        }

        if (empty($queries)) {
            echo "No entities found to synchronize.\n";
            return;
        }

        $promises = [];

        foreach ($queries as $table => $sql) {
            echo "Synchronizing table '{$table}'...\n";
            $promises[] = $db->query($sql)->then(
                function () use ($table) {
                    echo "Table '{$table}' synced successfully.\n";
                },
                function (\Throwable $e) use ($table) {
                    echo "Failed to sync table '{$table}': " . $e->getMessage() . "\n";
                }
            );
        }

        \React\Promise\all($promises)->then(function () {
            echo "Schema synchronization completed.\n";
            Loop::get()->stop();
        });

        Loop::get()->run();
    }
}

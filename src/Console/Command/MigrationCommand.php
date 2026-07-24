<?php

namespace Trunk\Console\Command;

use Trunk\Database\Connection;
use Trunk\Database\Migrator;
use Trunk\Database\Schema\SchemaBuilder;

abstract class MigrationCommand extends Command
{
    protected function migrator(): Migrator
    {
        $this->app->bootProviders();

        $db = $this->app->getContainer()->get(Connection::class);
        $schema = new SchemaBuilder($db);
        $migrationsPath = $this->app->getBasePath() . '/database/migrations';

        return new Migrator($db, $schema, $migrationsPath);
    }
}

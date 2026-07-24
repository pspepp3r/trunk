<?php

namespace Trunk\Database;

use React\Promise\PromiseInterface;
use Trunk\Database\Schema\SchemaBuilder;

abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): PromiseInterface;

    abstract public function down(SchemaBuilder $schema): PromiseInterface;
}

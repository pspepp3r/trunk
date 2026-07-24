<?php

namespace Trunk\Providers;

use Trunk\Config\Repository;
use Trunk\Database\Connection;
use Trunk\ORM\EntityManager;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Connection::class, fn($c) => new Connection($c->get(Repository::class)));

        $this->container->singleton(EntityManager::class, fn($c) => new EntityManager($c->get(Connection::class)));
    }
}

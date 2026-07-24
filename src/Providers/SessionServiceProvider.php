<?php

namespace Trunk\Providers;

use Trunk\Config\Repository;
use Trunk\Session\Interface\SessionStoreInterface;
use Trunk\Session\MemorySessionStore;
use Trunk\Session\SessionMiddleware;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SessionStoreInterface::class, MemorySessionStore::class);

        $this->container->singleton(SessionMiddleware::class, fn($c) => new SessionMiddleware(
            $c->get(SessionStoreInterface::class),
            $c->get(Repository::class)
        ));
    }
}

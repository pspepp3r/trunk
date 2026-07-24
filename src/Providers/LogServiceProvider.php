<?php

namespace Trunk\Providers;

use Trunk\Config\Repository;
use Trunk\Log\Logger;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Logger::class, fn($c) => new Logger($c->get(Repository::class)));
    }
}

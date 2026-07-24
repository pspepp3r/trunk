<?php

namespace Trunk\Providers;

use Trunk\Event\Dispatcher;
use Trunk\Log\Logger;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Dispatcher::class, fn($c) => new Dispatcher($c->get(Logger::class)));
    }
}

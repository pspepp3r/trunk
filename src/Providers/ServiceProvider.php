<?php

namespace Trunk\Providers;

use Trunk\Container\Container;

abstract class ServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register any application services in the container.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services after all providers are registered.
     */
    public function boot(): void
    {
        // Optional boot step
    }
}

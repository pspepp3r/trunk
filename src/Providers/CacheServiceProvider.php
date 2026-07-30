<?php

namespace Trunk\Providers;

use Trunk\Cache\Interface\CacheInterface;
use Trunk\Cache\RedisCache;
use Trunk\Cache\RedisConnection;
use Trunk\Config\Repository;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->container->get(Repository::class);
        $driver = $config->get('cache.default');

        if ($driver === null || !$config->get("cache.stores.{$driver}")) {
            return;
        }

        if ($driver === 'redis') {
            $this->container->singleton(RedisConnection::class, fn($c) => new RedisConnection($c->get(Repository::class)));
            $this->container->singleton(CacheInterface::class, fn($c) => new RedisCache($c->get(RedisConnection::class)));
        }
    }
}

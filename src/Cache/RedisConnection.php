<?php

namespace Trunk\Cache;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Trunk\Config\Repository;

/**
 * Thin wrapper around clue/redis-react's lazy client, forwarding any Redis command
 * (INCR, EXPIRE, LPUSH, ...) as a method call. RedisCache builds the CacheInterface
 * contract on top of this; reach for this class directly if you need raw Redis
 * commands beyond simple get/set/delete caching.
 */
class RedisConnection
{
    private Client $client;

    public function __construct(Repository $config)
    {
        $host = $config->get('cache.stores.redis.host', '127.0.0.1');
        $port = $config->get('cache.stores.redis.port', 6379);

        $factory = new Factory();
        $this->client = $factory->createLazyClient("redis://{$host}:{$port}");
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->client->$name(...$arguments);
    }
}

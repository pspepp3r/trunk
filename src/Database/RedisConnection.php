<?php

namespace Trunk\Database;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Trunk\Config\Repository;

class RedisConnection
{
    private Client $client;

    public function __construct(Repository $config)
    {
        $host = $config->get('database.redis.host', '127.0.0.1');
        $port = $config->get('database.redis.port', 6379);
        
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

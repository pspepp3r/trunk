<?php

namespace Trunk\Cache;

use React\Promise\PromiseInterface;
use Trunk\Cache\Interface\CacheInterface;

class RedisCache implements CacheInterface
{
    public function __construct(private readonly RedisConnection $redis)
    {
    }

    public function get(string $key): PromiseInterface
    {
        return $this->redis->get($key)->then(
            fn($value) => $value === null ? null : json_decode($value, true)
        );
    }

    public function set(string $key, mixed $value, ?int $ttl = null): PromiseInterface
    {
        $encoded = json_encode($value);

        $promise = $ttl !== null
            ? $this->redis->setex($key, $ttl, $encoded)
            : $this->redis->set($key, $encoded);

        return $promise->then(fn() => true);
    }

    public function delete(string $key): PromiseInterface
    {
        return $this->redis->del($key)->then(fn($count) => ((int) $count) > 0);
    }

    public function has(string $key): PromiseInterface
    {
        return $this->redis->exists($key)->then(fn($count) => ((int) $count) > 0);
    }

    public function increment(string $key, int $by = 1): PromiseInterface
    {
        $promise = $by === 1 ? $this->redis->incr($key) : $this->redis->incrby($key, $by);

        return $promise->then(fn($value) => (int) $value);
    }

    public function expire(string $key, int $seconds): PromiseInterface
    {
        return $this->redis->expire($key, $seconds)->then(fn($result) => (bool) $result);
    }
}

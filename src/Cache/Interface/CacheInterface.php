<?php

namespace Trunk\Cache\Interface;

use React\Promise\PromiseInterface;

/**
 * A backend-agnostic async cache contract. Trunk ships Trunk\Cache\RedisCache as the
 * first implementation, but nothing in the framework depends on Redis directly through
 * this interface - implement it against Memcached, an in-process array, or anything
 * else and bind it in place of RedisCache in your own service provider.
 */
interface CacheInterface
{
    /**
     * Resolves to the stored value, or null if the key doesn't exist.
     */
    public function get(string $key): PromiseInterface;

    /**
     * Resolves to true on success. $ttl is seconds until expiry, or null to never expire.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): PromiseInterface;

    /**
     * Resolves to true if the key existed and was removed.
     */
    public function delete(string $key): PromiseInterface;

    /**
     * Resolves to true if the key exists.
     */
    public function has(string $key): PromiseInterface;

    /**
     * Atomically increments an integer counter (creating it at $by if absent) and
     * resolves to the new value. Used by RateLimitMiddleware.
     */
    public function increment(string $key, int $by = 1): PromiseInterface;

    /**
     * Sets (or resets) a key's time-to-live in seconds. Resolves to true on success.
     */
    public function expire(string $key, int $seconds): PromiseInterface;
}

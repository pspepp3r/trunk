# Cache

Trunk's cache layer is a single backend-agnostic contract, `Trunk\Cache\Interface\CacheInterface` - everything that uses a cache (like [`RateLimitMiddleware`](/guide/middleware#rate-limiting-cache-backed)) depends on that interface, not on Redis or any other specific store. Redis is the only implementation Trunk ships today, but you can bind your own (Memcached, an in-process array, anything) in its place without touching anything that consumes `CacheInterface`.

## Configuring a store

`config/cache.php`:

```php
return [
    'default' => $_ENV['CACHE_DRIVER'] ?? null,

    'stores' => [
        'redis' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
        ],
    ],
];
```

And in `.env`:

```dotenv
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Leave `CACHE_DRIVER` blank (or remove the matching `stores` entry) to skip cache wiring entirely - `Trunk\Providers\CacheServiceProvider` only binds `CacheInterface` when both `cache.default` and its matching `cache.stores.{driver}` block are present, so an app that doesn't use caching pays no cost for it.

Using Redis requires installing it yourself, matching every other optional driver in Trunk:

```bash
composer require clue/redis-react
```

## Using the cache

Type-hint `CacheInterface` anywhere it's autowired (a controller, a middleware, another service):

```php
use Trunk\Cache\Interface\CacheInterface;

class ReportController
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function index(): PromiseInterface
    {
        return $this->cache->get('report:summary')->then(function ($cached) {
            if ($cached !== null) {
                return Response::json($cached);
            }

            return $this->buildReport()->then(function ($report) {
                $this->cache->set('report:summary', $report, ttl: 300); // 5 minutes
                return Response::json($report);
            });
        });
    }
}
```

`CacheInterface` methods, all Promise-returning:

| Method | Resolves to |
| --- | --- |
| `get(string $key)` | The stored value, or `null` if missing. |
| `set(string $key, mixed $value, ?int $ttl = null)` | `true`. `$ttl` is seconds until expiry, or `null` to never expire. |
| `delete(string $key)` | `true` if the key existed and was removed. |
| `has(string $key)` | `true` if the key exists. |
| `increment(string $key, int $by = 1)` | The counter's new integer value (creates it at `$by` if absent). |
| `expire(string $key, int $seconds)` | `true` on success. |

`RedisCache` JSON-encodes values on `set()` and decodes them on `get()`, so arrays and scalars round-trip transparently.

## Raw Redis access

If you need a Redis command `CacheInterface` doesn't expose (pub/sub, lists, sorted sets, ...), reach for `Trunk\Cache\RedisConnection` directly - it forwards any method call as the matching Redis command and returns a promise:

```php
$redis = $app->getContainer()->get(\Trunk\Cache\RedisConnection::class);

$redis->lpush('queue:jobs', $payload);
$redis->publish('events', $message);
```

## Adding another backend

Implement `CacheInterface` against whatever you want (Memcached, APCu, an in-process array for tests) and bind it in your own service provider instead of relying on `CacheServiceProvider`'s Redis binding:

```php
$this->container->singleton(CacheInterface::class, fn($c) => new MemcachedCache(/* ... */));
```

Everything that depends on `CacheInterface` - `RateLimitMiddleware` included - works unchanged against it.

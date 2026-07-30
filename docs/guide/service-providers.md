# Service Providers

Service providers are where the framework (and your app) wires things into the [container](/guide/container) - every default subsystem (database, cache, sessions, events, logging, auth) is registered by one, and you write your own the same way to register your own services or override a default binding.

## The base class

```php
namespace Trunk\Providers;

use Trunk\Container\Container;

abstract class ServiceProvider
{
    public function __construct(protected Container $container) {}

    abstract public function register(): void;

    public function boot(): void
    {
        // optional
    }
}
```

- **`register()`** is where you bind things into the container - it should only bind, not resolve anything that depends on another provider having already registered (registration order between providers isn't guaranteed at this stage).
- **`boot()`** runs after *every* provider's `register()` has completed, so it's the safe place to do anything that depends on another provider's bindings existing. It's optional - most providers don't need it.

## The providers Trunk ships

`App::configure()` registers these by default, in this order, before your own `config/app.php` providers:

```php
Trunk\Providers\LogServiceProvider::class,
Trunk\Providers\DatabaseServiceProvider::class,
Trunk\Providers\CacheServiceProvider::class,
Trunk\Providers\SessionServiceProvider::class,
Trunk\Providers\EventServiceProvider::class,
Trunk\Providers\AuthServiceProvider::class,
```

Each one is a small, readable example of the pattern - `Trunk\Providers\CacheServiceProvider`, for instance, reads `cache.default` from config and only binds `CacheInterface` if a driver is actually configured:

```php
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
```

## Writing your own

```php
namespace App\Providers;

use Trunk\Providers\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ReportGenerator::class, fn ($c) => new ReportGenerator(
            $c->get(\Trunk\ORM\EntityManager::class),
        ));
    }
}
```

Register it in `config/app.php`:

```php
return [
    // ...
    'providers' => [
        \App\Providers\ReportingServiceProvider::class,
    ],
];
```

Your providers run *after* the six defaults above, so binding an ID a default provider already bound (`SessionStoreInterface`, for example) replaces it - this is the supported way to swap a default implementation for your own, without touching framework code.

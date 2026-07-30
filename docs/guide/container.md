# Dependency Injection Container

Nearly every other page in this guide says "autowired" or "resolved through the container" without explaining what that means - this page is that explanation. `Trunk\Container\Container` implements PSR-11 (`Psr\Container\ContainerInterface`) and is what resolves controllers, middleware, commands, and service constructors throughout the framework.

## Autowiring by reflection

Ask the container for a class it doesn't know about, and it inspects that class's constructor with reflection, resolves each typed parameter recursively (calling `get()` on itself for each one), and instantiates it - no configuration needed for the common case:

```php
class ReportGenerator
{
    public function __construct(
        private readonly Trunk\ORM\EntityManager $em,
        private readonly Trunk\Log\Logger $logger,
    ) {}
}

$container->get(ReportGenerator::class); // both dependencies resolved automatically
```

This is why a controller, middleware class, or console command can simply type-hint `EntityManager`, `Connection`, `Dispatcher`, or any other framework/app class in its constructor and get a working instance with no manual wiring - the `Router`, `Pipeline`, and `Kernel` all resolve their targets through the same container.

A parameter the container can't resolve - a scalar with no default, or an interface with no binding - throws `Trunk\Container\Exception\ContainerException` with the parameter name, rather than failing silently.

## Binding an interface to an implementation

Autowiring only works for concrete classes; the container has no way to guess which implementation you want for an interface. Bind it explicitly, typically from a `ServiceProvider`'s `register()` (see [Service Providers](/guide/service-providers)):

```php
use Trunk\Cache\Interface\CacheInterface;
use Trunk\Cache\RedisCache;

$container->set(CacheInterface::class, fn ($c) => new RedisCache($c->get(RedisConnection::class)));
```

- **`set($id, $value)`** stores a raw value or a factory closure. A closure is invoked (with the container passed in) *every time* `get()` is called for that ID - useful when you want a fresh instance per resolution.
- **`singleton($id, $value)`** wraps the value so it's resolved once and cached for the lifetime of the container - the common case for services like a database connection or cache client that should be shared everywhere. `$value` can be a closure, a class name string (resolved via `get()` the first time), or a plain value.

```php
$container->singleton(Connection::class, fn ($c) => new Connection($c->get(Repository::class)));
```

Registering the same ID again (from a later-registered provider, for instance) simply replaces the earlier binding - there's no error for overwriting, which is how you'd swap out a default binding (like the session store - see [Sessions](/guide/sessions)) for your own implementation.

## Looking things up directly

```php
$container->get(SomeClass::class);      // resolve (or return the bound value/singleton)
$container->has(SomeClass::class);      // true if bound OR if the class exists and could be autowired
```

`get()` throws `Trunk\Container\Exception\NotFoundException` if the ID isn't bound and isn't an existing class name.

You'll rarely call `get()`/`has()` directly in application code - constructor injection through autowiring is the normal path. Direct container access shows up mostly inside service providers and framework internals (`App`, `Router`, `Pipeline`) where there's no constructor to inject into.

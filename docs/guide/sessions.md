# Sessions

Trunk ships a cookie-backed session as one of the [always-on middleware](/guide/middleware#built-in-always-on-middleware) - every request gets a `Trunk\Session\Session` instance attached as the `session` request attribute, no setup required.

## Reading and writing

```php
public function index(ServerRequestInterface $request): ReactResponse
{
    /** @var Trunk\Session\Session $session */
    $session = $request->getAttribute('session');

    $visits = $session->get('visits', 0) + 1;
    $session->set('visits', $visits);

    return Response::json(['visits' => $visits]);
}
```

- **`get(string $key, mixed $default = null)`** - reads a value, including anything flashed on the *previous* request (see below).
- **`set(string $key, mixed $value)`** - writes a value, persisted for the current session going forward.
- **`remove(string $key)`** - deletes a key from both current and flashed data.
- **`flash(string $key, mixed $value)`** - stores a value that's readable via `get()` on the *next* request only, then discarded - the standard pattern for one-time messages ("user created successfully") that shouldn't survive a page refresh.
- **`regenerate()`** - assigns a new random session ID, e.g. after a login, to avoid session fixation. The old ID's stored data isn't deleted automatically - if you need that, call your store's `destroy()` with the old ID before regenerating.
- **`isModified()`** - true if anything was written this request; `SessionMiddleware` uses this to skip an unnecessary store write when a request never touched the session.

## How it's wired

`SessionMiddleware` reads the session ID from a cookie (`session.cookie`, default `trunk_session`), loads that session's data from whatever `SessionStoreInterface` is bound, attaches the `Session` object to the request, and - after your handler runs - writes it back to the store (if modified) and sets the cookie on the response:

```php
// config/session.php
return [
    'driver' => $_ENV['SESSION_DRIVER'] ?? 'memory',
    'lifetime' => $_ENV['SESSION_LIFETIME'] ?? 3600,
    'cookie' => $_ENV['SESSION_COOKIE'] ?? 'trunk_session',
];
```

If this file doesn't exist at all, `SessionMiddleware` still runs - it falls back to `trunk_session`/3600s via `config()`'s default-value argument, so sessions work even in a trimmed-down app with no `config/session.php`.

## Swapping the session store

The default `SessionStoreInterface` binding is `Trunk\Session\MemorySessionStore` - a plain in-process array, registered by `SessionServiceProvider`. That means session data doesn't survive a restart and isn't shared across multiple Trunk processes - fine for a single-process dev setup, not for anything running more than one process or needing sessions to survive a redeploy.

To use something else (Redis, a database table, anything), implement `SessionStoreInterface`:

```php
interface SessionStoreInterface
{
    public function get(string $id): array;
    public function set(string $id, array $data): void;
    public function destroy(string $id): void;
}
```

and bind it from your own [service provider](/guide/service-providers), registered after the defaults so it overrides `SessionServiceProvider`'s binding:

```php
class RedisSessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SessionStoreInterface::class, fn ($c) => new RedisSessionStore(
            $c->get(\Trunk\Cache\RedisConnection::class),
        ));
    }
}
```

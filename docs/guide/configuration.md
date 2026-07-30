# Configuration

Every `config/*.php` file returns a plain PHP array, loaded once at boot into a `Trunk\Config\Repository` and read anywhere through either the `config()` helper (see [Helpers](/guide/helpers)) or the injected `Repository` itself.

## How files are loaded

`App::configure($basePath)` scans `{$basePath}/config/*.php`, `require`s each file, and stores its returned array under a key matching the filename (without `.php`):

```text
config/app.php       -> config('app.*')
config/database.php  -> config('database.*')
config/auth.php      -> config('auth.*')
```

So `config/database.php` returning `['default' => 'mysql', 'connections' => [...]]` makes `config('database.default')` and `config('database.connections.mysql.host')` both work.

## Reading values

```php
config('app.port');                    // '8080'
config('database.default', 'mysql');   // second argument is the default if the key is missing
config()->all();                       // the whole merged config array, if you inject Repository directly
```

Keys use dot notation to walk into nested arrays - `config('database.connections.mysql.host')` looks up `$configArray['database']['connections']['mysql']['host']`. A missing key at any point in the path returns the default (`null` if you didn't pass one) rather than throwing - config lookups are deliberately forgiving, since most values have a sane fallback anyway.

## `.env` and config files

`.env` values aren't read directly by application code - by convention, every `config/*.php` file reads `$_ENV` once and falls back to a hardcoded default:

```php
// config/database.php
return [
    'connections' => [
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            // ...
        ],
    ],
];
```

`.env` is loaded (via `vlucas/phpdotenv`) before config files are read, so `$_ENV` is already populated by the time they run. This indirection means your application code only ever calls `config('database.connections.mysql.host')` - never `$_ENV['DB_HOST']` directly - which keeps environment variable names as an implementation detail of the config file, not something scattered across your codebase.

## Injecting `Repository` directly

Anywhere autowiring works (controllers, middleware, providers, commands), you can type-hint `Trunk\Config\Repository` instead of using the global `config()` helper - useful in a service provider, where the container isn't fully booted yet and you want the exact same lookup API:

```php
use Trunk\Config\Repository;

class SomeService
{
    public function __construct(private readonly Repository $config) {}
}
```

`Repository` also has `set(string $key, mixed $value)`, using the same dot notation, if you need to mutate config at runtime (rare - mostly useful in tests).

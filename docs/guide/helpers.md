# Helpers

A handful of global functions, autoloaded via `composer.json`'s `files` autoload entry, available everywhere without an import.

## `config()`

```php
config(?string $key = null, mixed $default = null): mixed
```

Shorthand for resolving the running app's `Trunk\Config\Repository` and calling `get()` on it - see [Configuration](/guide/configuration) for the full lookup behavior (dot notation, defaults, `.env` interaction). Calling `config()` with no arguments returns the `Repository` instance itself, so `config()->all()` works too.

## `base_path()`

```php
base_path(string $path = ''): string
```

The absolute path to your application's root directory (wherever `bootstrap/app.php` called `App::configure()` from), or a path beneath it:

```php
base_path();                  // /var/www/my-app
base_path('storage/logs');    // /var/www/my-app/storage/logs
```

## `database_path()`

```php
database_path(string $path = ''): string
```

Shorthand for `base_path('database' . ...)`  - used by default config (e.g. the SQLite connection's default `database` path) and migration/entity code that needs a path under `database/` without hardcoding how deep the app lives on disk:

```php
database_path();                    // /var/www/my-app/database
database_path('database.sqlite');   // /var/www/my-app/database/database.sqlite
```

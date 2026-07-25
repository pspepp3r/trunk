# Getting Started

Trunk is split into two packages:

- **`trunk/core`** - the framework itself (this repo).
- **A skeleton app** - a starter project that depends on `trunk/core` and is where you actually build your API.

## Requirements

- PHP 8.1+
- Composer
- MySQL or PostgreSQL (optional - only needed if your app touches the database)

## Creating a new app

```bash
composer create-project trunk/skeleton my-app
cd my-app
```

This pulls the skeleton from Packagist, installs dependencies, and copies `.env.example` to `.env` for you - with a fresh, randomly-generated `JWT_SECRET` already filled in (see [Console](/guide/console) for the `key:generate` command doing this behind the scenes).

Key `.env` values to review:

```dotenv
APP_PORT=8080

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=
```

`JWT_SECRET` is already set for you, but if you ever need a new one (rotating it, or setting up a second environment), regenerate it rather than typing one by hand - HS256 requires at least 32 bytes and rejects shorter keys:

```bash
php trunk key:generate
```

## Running the server

```bash
php trunk start
```

This boots a ReactPHP HTTP server on `127.0.0.1:{APP_PORT}` (default `8080`). Unlike PHP-FPM, this process stays running and handles every request through the same event loop - there's no per-request bootstrap cost, but it also means a blocking call anywhere in your request path stalls every other in-flight request. See [Routing](/guide/routing) and [gRPC Client](/guide/grpc) for how Trunk avoids that.

## Project layout

```text
your-app/
  bootstrap/app.php     # builds the App, loads config + routes
  config/                # app.php, database.php, auth.php, events.php, routes.php, ...
  public/index.php       # front controller (used by `php trunk start`)
  src/
    Controllers/
    Entities/            # ORM entities (implement Trunk\ORM\Interface\EntityInterface)
    Requests/             # FormRequest subclasses
    Middleware/
    Events/ Listeners/
  database/migrations/    # created by `php trunk make:migration`
  trunk                   # the CLI entrypoint (`php trunk <command>`)
```

## A minimal route

```php
// config/routes.php
use Trunk\App;
use Trunk\Http\Response;

return function (App $app) {
    $app->get('/ping', function () {
        return Response::json(['pong' => true]);
    });
};
```

From here:

- [Routing](/guide/routing) - path params, route model binding, per-route middleware.
- [Validation](/guide/validation) - `FormRequest` for validating incoming data.
- [Database & Migrations](/guide/database) - configuring a driver, writing migrations, using the ORM.
- [Console (CLI)](/guide/console) - every `php trunk` command.

# Getting Started

Trunk is split into two packages:

- **`trunk/core`** - the framework itself (this repo).
- **A skeleton app** - a starter project that depends on `trunk/core` and is where you actually build your API.

## Requirements

- PHP 8.1+
- Composer
- MySQL or PostgreSQL (optional - only needed if your app touches the database)

## Creating a new app

Clone or copy the skeleton app, then install its dependencies:

```bash
composer install
```

Copy the environment file and adjust it for your database, JWT secret, etc.:

```bash
cp .env.example .env
```

Key `.env` values:

```dotenv
APP_PORT=8080

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=change-me-to-a-random-32-plus-byte-string
```

`JWT_SECRET` must be at least 32 bytes (256 bits) - HS256 rejects shorter keys.

## Running the server

```bash
php trunk start
```

This boots a ReactPHP HTTP server on `127.0.0.1:{APP_PORT}` (default `8080`). Unlike PHP-FPM, this process stays running and handles every request through the same event loop - there's no per-request bootstrap cost, but it also means a blocking call anywhere in your request path stalls every other in-flight request. See [Routing](/guide/routing) and [gRPC Client](/guide/grpc) for how Trunk avoids that.

## Project layout

```
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

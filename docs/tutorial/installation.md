# Installation & Your First Route

Trunk is split into two packages:

- **`trunk/core`** - the framework itself.
- **A skeleton app** (`trunk/skeleton`) - a starter project that depends on `trunk/core` and is where you actually build your API. It ships with a real, working JWT register/login flow (see [Adding Authentication](/tutorial/authentication)) - not a blank slate.

## Requirements

- PHP 8.1+
- Composer
- MySQL or PostgreSQL (optional - only needed if your app touches the database)

## Creating a new app

```bash
composer create-project trunk/skeleton my-app
cd my-app
```

This pulls the skeleton from Packagist, installs dependencies, and runs an interactive installer that asks which database you want (MySQL, PostgreSQL, or None) so you only install the driver you actually need.

It also copies `.env.example` to `.env` for you - with a fresh, randomly-generated `JWT_SECRET` already filled in (see [Console](/guide/console) for the `key:generate` command doing this behind the scenes).

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

If `DB_DATABASE` doesn't exist on your database server yet, create it before migrating - Trunk creates tables for you, but not the database itself:

```bash
php trunk db:create
php trunk migrate
```

## Running the server

```bash
php trunk start
```

Or in watch mode, so it automatically restarts on file changes under `src/`, `config/`, and `bootstrap/`:

```bash
php trunk start --watch
```

This boots a ReactPHP HTTP server on `127.0.0.1:{APP_PORT}` (default `8080`) - see [Introduction](/tutorial/introduction) for why this process model means a blocking call anywhere in your request path stalls every other in-flight request, and how Trunk's async, promise-based APIs avoid that.

## Project layout

```text
your-app/
  bootstrap/app.php     # builds the App, loads config + routes
  config/                # app.php, database.php, auth.php, events.php, routes.php, ...
  public/index.php       # front controller (used by `php trunk start`)
  src/
    Controllers/
    Entities/            # ORM entities extending Trunk\ORM\BaseEntity
    Requests/             # FormRequest subclasses
    Middleware/
  database/migrations/    # created by `php trunk make:migration` or `orm:schema-diff`
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

Start the server and confirm it:

```bash
curl http://127.0.0.1:8080/ping
# {"pong":true}
```

## Troubleshooting

**`Unknown database 'my_app'`** - `php trunk migrate` doesn't create the database itself, only tables inside it. Run `php trunk db:create` first (see [Console](/guide/console#database) for what it does under each driver).

**`Class "React\Mysql\MysqlClient" not found`** - the stable release of `react/mysql` (v0.6.x) doesn't have this class; it only exists on the `0.7.x`/`0.8.x` dev branches. The skeleton's installer pins this correctly (`react/mysql:^0.7 || ^0.8`) - if you see this error, you likely ran a bare `composer require react/mysql` yourself instead of letting the installer (or this exact constraint) handle it.

**JWT errors on boot** (`invalid key length` or similar) - `JWT_SECRET` must be at least 32 bytes for HS256. Run `php trunk key:generate` rather than setting it by hand.

**SQLite path silently resolves to the wrong file on Windows** - see the callout in [Database & Migrations](/guide/database) if you're using `sqlite` as your connection - it's a `clue/reactphp-sqlite` path-parsing quirk specific to Windows, and Trunk works around it automatically as long as you're not constructing the path in an unusual way.

## From here

- [Building a Resource](/tutorial/building-a-resource) - the next tutorial page: a migration, an entity, validation, and a controller, end to end.
- [Adding Authentication](/tutorial/authentication) - the register/login flow the skeleton ships with.
- [Routing](/guide/routing), [Validation](/guide/validation), [Database & Migrations](/guide/database) - reference pages for what you'll use along the way.

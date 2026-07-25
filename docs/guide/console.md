# Console (CLI)

Every command runs through the `trunk` script in your app root:

```bash
php trunk <command> [arguments] [options]
php trunk help
```

## Server

| Command | Description |
| --- | --- |
| `start` | Boots the ReactPHP HTTP server on `127.0.0.1:{app.port}`. |
| `route:list` | Prints every registered route, method, and path. |

## Code generation

| Command | Description |
| --- | --- |
| `make:controller <Name>` | Creates `src/Controllers/{Name}.php`. |
| `make:middleware <Name>` | Creates `src/Middleware/{Name}.php` implementing `MiddlewareInterface`. |
| `make:migration <Name>` | Creates a timestamped migration in `database/migrations/`. The table name is guessed from a `Create{X}Table` naming convention. |

## Database

| Command | Description |
| --- | --- |
| `db:create` | Creates the database named in `database.connections.{driver}.database` if it doesn't already exist yet. Safe to run repeatedly. Run this once before your first `migrate` on a fresh setup. |
| `migrate` | Runs all pending migrations, in order. |
| `migrate:status` | Lists every migration with `Ran (batch N)` or `Pending`. |
| `migrate:rollback [--step=N]` | Rolls back the last batch (or the last `N` batches). |
| `schema:sync` | Reflects on `src/Entities/` and runs `CREATE TABLE IF NOT EXISTS` for each - a blunt, no-history shortcut for early prototyping. Prefer migrations once your schema needs to evolve. |

See [Database & Migrations](/guide/database) for the Blueprint DSL used inside generated migrations.

## Security

| Command | Description |
| --- | --- |
| `key:generate` | Generates a random 32-byte `JWT_SECRET` and writes it into `.env` (replacing any existing value). Prints the value instead if no `.env` exists yet. |

See [Authentication](/guide/authentication) for how `JWT_SECRET` is used.

## Notes

- Commands that touch the database (`migrate*`, `schema:sync`) boot service providers and run the ReactPHP event loop for the duration of the command, then exit cleanly.
- `db:create` supports `mysql` and `pgsql` only. MySQL connects without selecting a database and runs `CREATE DATABASE IF NOT EXISTS`; Postgres has no such clause, so it connects to the `postgres` maintenance database, checks `pg_database` first, and only creates it if missing. The database user needs `CREATE DATABASE` privileges either way.
- `make:*` commands are purely file generators - they don't touch the database or require it to be configured.

## Writing your own commands

Every command is its own class implementing `Trunk\Console\Command\Interface\CommandInterface` - there's no giant switch statement to extend. The `Command` base class gives you `$this->app` (the same `App` instance the rest of the framework uses), autowired like everything else:

```php
namespace App\Console\Command;

use Trunk\Console\Command\Command;

class PingCommand extends Command
{
    public static function description(): string
    {
        return 'Print pong';
    }

    public function handle(array $args): void
    {
        echo "pong\n";
    }
}
```

`$args` is the raw `$argv` array, so `$args[2] ?? null` gets the first argument after the command name (see `MakeControllerCommand` for an example).

Register it in `Kernel`'s command map (`trunk/src/Console/Kernel.php`) under whatever name you want to type on the command line:

```php
'ping' => \App\Console\Command\PingCommand::class,
```

Since commands are resolved through the container (`$container->get($commandClass)`), constructor dependencies beyond `App` are autowired the same as controllers and middleware - type-hint `EntityManager`, `Connection`, or anything else you need.

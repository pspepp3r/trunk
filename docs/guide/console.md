# Console (CLI)

Every command runs through the `trunk` script in your app root:

```bash
php trunk <command> [arguments] [options]
php trunk help
```

## Server

| Command | Description |
|---|---|
| `start` | Boots the ReactPHP HTTP server on `127.0.0.1:{app.port}`. |
| `route:list` | Prints every registered route, method, and path. |

## Code generation

| Command | Description |
|---|---|
| `make:controller <Name>` | Creates `src/Controllers/{Name}.php`. |
| `make:middleware <Name>` | Creates `src/Middleware/{Name}.php` implementing `MiddlewareInterface`. |
| `make:migration <Name>` | Creates a timestamped migration in `database/migrations/`. The table name is guessed from a `Create{X}Table` naming convention. |

## Database

| Command | Description |
|---|---|
| `migrate` | Runs all pending migrations, in order. |
| `migrate:status` | Lists every migration with `Ran (batch N)` or `Pending`. |
| `migrate:rollback [--step=N]` | Rolls back the last batch (or the last `N` batches). |
| `schema:sync` | Reflects on `src/Entities/` and runs `CREATE TABLE IF NOT EXISTS` for each - a blunt, no-history shortcut for early prototyping. Prefer migrations once your schema needs to evolve. |

See [Database & Migrations](/guide/database) for the Blueprint DSL used inside generated migrations.

## Notes

- Commands that touch the database (`migrate*`, `schema:sync`) boot service providers and run the ReactPHP event loop for the duration of the command, then exit cleanly.
- `make:*` commands are purely file generators - they don't touch the database or require it to be configured.

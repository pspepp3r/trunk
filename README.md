<p align="center">
  <img src="docs/public/logo.svg" alt="Trunk" width="120">
</p>

<h1 align="center">Trunk</h1>

<p align="center">An async, API-centric PHP framework built on <a href="https://reactphp.org">ReactPHP</a>.</p>

---

Trunk runs your API on a single, non-blocking event loop instead of PHP-FPM's one-process-per-request model. The router, middleware pipeline, ORM, sessions, and CLI all share that loop, so a slow database query doesn't tie up an entire worker - it ties up nothing, because nothing is blocked while it's in flight.

## Features

- **DI container** with constructor autowiring by reflection, plus singleton/factory bindings.
- **Router** with path params, [route model binding](docs/guide/routing.md#route-model-binding), and per-route middleware.
- **Validation** via `FormRequest` classes that short-circuit to a `422` before your handler runs.
- **Database layer** for MySQL and PostgreSQL behind one API, with a **migrations** system (`Schema`/`Blueprint` DSL) and a Data Mapper **ORM**.
- **JWT authentication** and a CORS/JSON-body-parsing/logging/session middleware stack, on by default.
- **Events**, object-typed, with config-driven listener registration and a non-blocking `dispatchAsync()`.
- **GraphQL** endpoint (via `webonyx/graphql-php`'s async promise adapter) sharing the same ORM queries as REST.
- **gRPC client** support via a child-process bridge - PHP can't run a gRPC *server*, so this lets you call one without blocking the loop.
- **Console** (`php trunk ...`) for the dev server, code generation, and migrations.

## Installation

Trunk is consumed through a skeleton application, not installed standalone. See the skeleton's README to get a project running, or [Getting Started](docs/guide/getting-started.md) for the full walkthrough.

## Documentation

The full guide lives in [`docs/`](docs) - run `npm install && npm run docs:dev` inside that directory for the local site, or just read the Markdown directly:

- [Getting Started](docs/guide/getting-started.md)
- [Routing](docs/guide/routing.md) · [Middleware](docs/guide/middleware.md) · [Validation](docs/guide/validation.md)
- [Database & Migrations](docs/guide/database.md) · [Authentication](docs/guide/authentication.md)
- [Events](docs/guide/events.md) · [GraphQL](docs/guide/graphql.md) · [gRPC Client](docs/guide/grpc.md)
- [Console (CLI)](docs/guide/console.md) · [Testing](docs/guide/testing.md)

## Testing

```bash
composer install
composer test
```

## License

MIT

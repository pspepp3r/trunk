# Introduction

Trunk is an async, API-centric PHP framework built on [ReactPHP](https://reactphp.org/). If you're coming from Laravel, Symfony, or another PHP-FPM-based framework, the biggest adjustment isn't syntax - it's the process model. This page explains that model before the rest of the tutorial has you writing code against it.

## PHP-FPM vs. a long-running event loop

A traditional PHP app (under PHP-FPM, or `php -S`) starts fresh for every request: bootstrap, handle, tear down, repeat. Concurrency comes from the web server running many of these processes side by side - if one request blocks on a slow database query, it only blocks *that* process; others keep serving requests independently.

Trunk doesn't work that way. `php trunk start` boots **one PHP process** that keeps running, handling every request through the same [ReactPHP](https://reactphp.org/) event loop:

- **No per-request bootstrap cost** - your container, config, and service providers are built once at startup, not on every request.
- **One blocking call stalls everything.** If any code in a request's path makes a blocking call - a synchronous `file_get_contents()` to a slow URL, a classic PDO query, `sleep()` - the entire process is stuck on it, and *every other in-flight request* waits too. There's no other process to pick up the slack.

This is why Trunk's database drivers, HTTP client usage, and gRPC bridge are all built around `React\Promise\PromiseInterface` rather than returning results directly - a promise represents "this will resolve later, without blocking the event loop in the meantime." Nearly every I/O-touching method in this framework - `Connection::query()`, `Repository::find()`, `GrpcClient::callAsync()` - returns one.

```php
// This resolves later, asynchronously - other requests keep being served while it's in flight.
$connection->query('SELECT * FROM users WHERE id = ?', [$id])
    ->then(function (QueryResult $result) {
        return $result->rows;
    });
```

You'll see `->then()` throughout this tutorial. If you need to treat a promise's result as an ordinary return value (in a test, for instance), `react/async`'s `await()` blocks *only the current coroutine*, not the whole event loop - see [Testing](/guide/testing) for where that's the right tool.

## What "API-centric" means here

Trunk has no templating engine, no asset pipeline, no server-rendered views - every route returns a `Trunk\Http\Response` (almost always JSON). If you need a full-stack app with server-rendered HTML, this isn't the right tool; if you're building a REST or GraphQL API backend, that focus means less to configure and fewer layers between your code and the response.

## How this tutorial is organized

The rest of the Tutorial section walks through one continuous example - installing Trunk, building a real resource end to end (migration → entity → validation → controller → route), and wiring up the register/login flow the skeleton ships with. Each Reference page (in the sidebar below Tutorial) covers one feature in depth and stands alone - come back to those whenever you need the full API for something you've already seen used here.

Next: [Installation](/tutorial/installation).

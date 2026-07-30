# Adding Authentication

Unlike the resource you built on the previous page, this one you don't have to write - the skeleton ships a complete, working register/login flow out of the box. This page walks through what's already there and how to use it, since you've been relying on its output (a bearer token) since [Building a Resource](/tutorial/building-a-resource).

## What's already wired up

Three routes, in `config/routes.php`:

```php
$app->post('/register', [AuthController::class, 'register']);
$app->post('/login', [AuthController::class, 'login']);
$app->get('/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
```

- **`POST /register`** - validates `name`/`email`/`password` (see `src/Requests/RegisterRequest.php`), rejects an already-used email with `409`, hashes the password with `password_hash()`, persists a `User` via the ORM, and returns a token.
- **`POST /login`** - looks the user up by email, verifies the password with `password_verify()`, and returns a token on success or `401` on failure.
- **`GET /me`** - a protected example route: `AuthMiddleware` verifies the bearer token first, and the controller reads the user ID back out of the token's claims.

## Try it

```bash
curl -X POST http://127.0.0.1:8080/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.com","password":"secret123"}'
# {"token":"...","user":{"id":1,"name":"Ada","email":"ada@example.com"}}

TOKEN=$(curl -s -X POST http://127.0.0.1:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"secret123"}' | jq -r .token)

curl http://127.0.0.1:8080/me -H "Authorization: Bearer $TOKEN"
# {"id":1,"name":"Ada","email":"ada@example.com"}

curl http://127.0.0.1:8080/me
# {"error":"Unauthorized","message":"Missing bearer token"}
```

## Protecting your own routes

Add `AuthMiddleware` to any route (or globally, in `config/middleware.php`) the same way `/me` uses it:

```php
$app->post('/posts', [PostController::class, 'store'], [AuthMiddleware::class]);
```

Inside a protected handler, the decoded token claims are on the request:

```php
$claims = $request->getAttribute('auth');
$claims['sub']; // the authenticated user's ID
```

This is exactly what `PostController::store()` did back in [Building a Resource](/tutorial/building-a-resource) to figure out who's creating a post.

## Where to go deeper

[Authentication](/guide/authentication) is the full reference - how the JWT secret is configured and rotated, the complete `AuthController` walkthrough, and what's deliberately out of scope (authorization/roles, which Trunk doesn't prescribe).

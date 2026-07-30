# Authentication

Trunk ships JWT bearer-token auth as a core service (`Trunk\Auth\JwtTokenService`), plus a real, reusable `AuthMiddleware` and (in the skeleton) a working register/login flow backed by the ORM - not pseudo-code you have to build from scratch.

## Configuring the secret

`config/auth.php`:

```php
return [
    'secret' => $_ENV['JWT_SECRET'] ?? 'trunk-insecure-default-secret-please-change-me',
    'algo' => $_ENV['JWT_ALGO'] ?? 'HS256',
    'ttl' => (int) ($_ENV['JWT_TTL'] ?? 3600),
];
```

`JWT_SECRET` **must** be at least 32 bytes - HS256 requires a 256-bit key and throws if it's shorter. Generate one instead of typing it by hand:

```bash
php trunk key:generate
```

This writes a random 32-byte secret (base64-encoded) directly into `JWT_SECRET` in your `.env`, replacing whatever was there. If no `.env` file exists yet, it prints the value instead so you can add it yourself.

## The shipped register/login flow

The skeleton's `AuthController` (`src/Controllers/AuthController.php`) implements a complete, real flow against a `users` table - not a demo:

```php
use Trunk\Auth\Interface\TokenServiceInterface;
use Trunk\ORM\EntityManager;

class AuthController
{
    public function __construct(
        private readonly TokenServiceInterface $tokens,
        private readonly EntityManager $em,
    ) {}

    public function register(RegisterRequest $request): PromiseInterface { /* ... */ }
    public function login(ServerRequestInterface $request): PromiseInterface { /* ... */ }
    public function me(ServerRequestInterface $request): PromiseInterface { /* ... */ }
}
```

- **`register()`** validates input via a `FormRequest` (`name`, `email`, `password`), rejects an already-registered email with `409`, hashes the password with PHP's `password_hash()` (bcrypt by default), persists the user via the ORM, and returns a token.
- **`login()`** looks the user up by email, verifies the password with `password_verify()`, and returns a token on success or `401` on failure - for either a wrong password or an unknown email, so a caller can't distinguish "no such user" from "wrong password."
- **`me()`** demonstrates a protected route: it reads the `sub` claim `AuthMiddleware` already decoded onto the request and returns that user.

`TokenServiceInterface` is registered as a singleton by `AuthServiceProvider` (a default core provider), so both it and `EntityManager` are autowired into the controller with no manual container wiring.

```php
// config/routes.php
$app->post('/register', [AuthController::class, 'register']);
$app->post('/login', [AuthController::class, 'login']);
$app->get('/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
```

`issue()` adds `iat`/`exp` claims automatically based on `auth.ttl`; `AuthController` puts the user's primary key in the `sub` claim.

## Protecting routes

`AuthMiddleware` (`src/Middleware/AuthMiddleware.php`) reads the `Authorization: Bearer <token>` header, verifies it, and attaches the decoded claims to the request as the `auth` attribute - or returns a `401` if it's missing or invalid:

```php
use App\Middleware\AuthMiddleware;

$app->get('/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
```

Apply it per-route (see [Routing](/guide/routing#per-route-middleware)) rather than globally, so public routes like `/register`, `/login`, and `/health` stay reachable.

Inside a protected handler:

```php
$claims = $request->getAttribute('auth');
$claims['sub']; // whatever you put in issue() - the user's ID, in the shipped flow
```

## Verifying a token manually

```php
use Trunk\Auth\Exception\InvalidTokenException;

try {
    $claims = $tokens->verify($token);
} catch (InvalidTokenException $e) {
    // missing, malformed, expired, or bad signature
}
```

## What's out of scope

This covers *authentication* (proving who a request is from) - it doesn't include *authorization* (roles, permissions, or per-resource access control). If your app needs that, build it on top of the `auth` request attribute `AuthMiddleware` already attaches (e.g. store a `role` claim at `issue()` time and check it in your own middleware or controller logic); Trunk doesn't prescribe a roles/policies system.

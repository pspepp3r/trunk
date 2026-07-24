# Authentication

Trunk ships JWT bearer-token auth as a core service (`Trunk\Auth\JwtTokenService`), plus an example `AuthMiddleware` you copy into your app and adapt.

## Configuring the secret

`config/auth.php`:

```php
return [
    'secret' => $_ENV['JWT_SECRET'] ?? 'trunk-insecure-default-secret-please-change-me',
    'algo' => $_ENV['JWT_ALGO'] ?? 'HS256',
    'ttl' => (int) ($_ENV['JWT_TTL'] ?? 3600),
];
```

`JWT_SECRET` **must** be at least 32 bytes - HS256 requires a 256-bit key and throws if it's shorter.

## Issuing a token

`TokenServiceInterface` is registered as a singleton by `AuthServiceProvider` (a default core provider), so it's autowirable anywhere:

```php
use Trunk\Auth\Interface\TokenServiceInterface;

class AuthController
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}

    public function login(ServerRequestInterface $request): ReactResponse
    {
        $body = $request->getParsedBody() ?? [];

        // ... verify credentials against your own user store ...

        $token = $this->tokens->issue(['sub' => $user->getEmail()]);

        return Response::json(['token' => $token]);
    }
}
```

`issue()` adds `iat`/`exp` claims automatically based on `auth.ttl`.

## Protecting routes

`AuthMiddleware` reads the `Authorization: Bearer <token>` header, verifies it, and attaches the decoded claims to the request as the `auth` attribute - or returns a `401` if it's missing or invalid:

```php
use App\Middleware\AuthMiddleware;

$app->post('/users', [UserController::class, 'create'], [AuthMiddleware::class]);
```

Apply it per-route (see [Routing](/guide/routing#per-route-middleware)) rather than globally, so public routes like `/login` and `/health` stay reachable.

Inside a protected handler:

```php
$claims = $request->getAttribute('auth');
$claims['sub']; // whatever you put in issue()
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

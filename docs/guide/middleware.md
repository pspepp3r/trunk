# Middleware

Middleware implements `Trunk\Middleware\Interface\MiddlewareInterface`:

```php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Middleware\Interface\MiddlewareInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        // before the handler runs...

        return $next($request)->then(function ($response) {
            // after the handler runs...
            return $response;
        });
    }
}
```

Generate a skeleton with the CLI:

```bash
php trunk make:middleware SomeMiddleware
```

## Built-in, always-on middleware

Trunk registers these globally for every request - they aren't optional per app:

- **CORS** - preflight handling and permissive headers by default.
- **JSON body parser** - decodes `application/json` bodies into the parsed body.
- **Logging** - structured request/response log lines with duration.
- **Sessions** - cookie-backed session, available via `$request->getAttribute('session')`.

## Global vs. per-route middleware

Register middleware for every request:

```php
$app->use(App\Middleware\SomeMiddleware::class);
```

Or scope it to one route (see [Routing](/guide/routing)):

```php
$app->post('/users', [UserController::class, 'create'], [AuthMiddleware::class]);
```

Per-route middleware is the right choice for anything that shouldn't apply everywhere - authentication being the common case, since public endpoints like `/health` or `/login` need to stay reachable.

## Resolution

Middleware classes are resolved through the DI container, so constructor dependencies are autowired the same as controllers:

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}
    // ...
}
```

See [Authentication](/guide/authentication) for the `AuthMiddleware` this framework ships as an example.

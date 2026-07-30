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
- **Sessions** - cookie-backed session, available via `$request->getAttribute('session')`. See [Sessions](/guide/sessions) for the full API.

## Global vs. per-route middleware

Register middleware for every request from `config/middleware.php` (loaded and invoked by `bootstrap/app.php` right after `configure()`, before routes):

```php
// config/middleware.php
use Trunk\App;

return function (App $app) {
    $app->use(App\Middleware\SomeMiddleware::class);
};
```

Or scope it to one route (see [Routing](/guide/routing)):

```php
$app->post('/users', [UserController::class, 'create'], [AuthMiddleware::class]);
```

Per-route middleware is the right choice for anything that shouldn't apply everywhere - authentication being the common case, since public endpoints like `/health` or `/login` need to stay reachable.

## Optional middleware

These ship with Trunk core but aren't registered anywhere by default - add them yourself, globally or per-route, only if you need them.

### Rate limiting (cache-backed)

`Trunk\Middleware\RateLimitMiddleware` limits requests per client IP using whatever `Trunk\Cache\Interface\CacheInterface` implementation is bound in the container (Redis by default), so the limit is shared correctly across multiple Trunk processes (e.g. behind a load balancer) rather than reset per-process like an in-memory counter would be. See [Cache](/guide/cache) for wiring up a cache store - this middleware doesn't care which backend is behind `CacheInterface`.

```php
use Trunk\Middleware\RateLimitMiddleware;

$app->use(RateLimitMiddleware::class);
// or scoped to one route:
$app->post('/users', [UserController::class, 'create'], [RateLimitMiddleware::class]);
```

Configure the limit via `config/rate_limit.php` (defaults to 60 requests per 60 seconds if the file doesn't exist):

```php
return [
    'max' => 60,     // requests
    'window' => 60,  // seconds
];
```

Requests over the limit get a `429 Too Many Requests` JSON response instead of reaching your handler. The bucket key is the client's `REMOTE_ADDR` - if you need to key by an authenticated user or API key instead, wrap or subclass it.

### Content-Type / Accept API versioning

`Trunk\Middleware\ContentTypeVersionMiddleware` reads a vendor media type version off the `Accept` header (falling back to `Content-Type`) and attaches it to the request as the `api_version` attribute, so a handler can branch on it:

```php
use Trunk\Middleware\ContentTypeVersionMiddleware;

$app->use(ContentTypeVersionMiddleware::class);
```

```php
public function index(ServerRequestInterface $request): ReactResponse
{
    $version = $request->getAttribute('api_version'); // e.g. "2"
    // ...
}
```

Both common conventions are supported:

```text
Accept: application/vnd.trunk.v2+json         (version embedded in the type)
Accept: application/vnd.trunk+json;version=2  (version as a parameter)
```

If neither pattern matches (or the header is missing), it falls back to `config('versioning.default', '1')` - set a `config/versioning.php` returning `['default' => '1']` to change that fallback.

## Resolution

Middleware classes are resolved through the DI container, so constructor dependencies are autowired the same as controllers:

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}
    // ...
}
```

See [Authentication](/guide/authentication) for `AuthMiddleware`, the real bearer-token middleware the skeleton ships and wires up.

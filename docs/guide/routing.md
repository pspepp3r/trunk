# Routing

Routes are registered on the `App` instance, usually from `config/routes.php`:

```php
use Trunk\App;
use App\Controllers\UserController;

return function (App $app) {
    $app->get('/users', [UserController::class, 'index']);
    $app->post('/users', [UserController::class, 'create']);
    $app->get('/users/{id}', [UserController::class, 'show']);
};
```

Supported verbs: `get`, `post`, `put`, `delete`, `patch`. A handler can be:

- `[ControllerClass::class, 'method']` - resolved through the DI container (constructor-autowired).
- `'ControllerClass@method'` - same thing, string form.
- A closure.

## Path parameters

`{name}` segments are matched and passed to the handler in order, after the request:

```php
$app->get('/users/{id}', function (ServerRequestInterface $request, string $id) {
    return Response::json(['id' => $id]);
});
```

## Route model binding

If a parameter's name matches a route segment **and** its type implements `Trunk\ORM\Interface\EntityInterface`, Trunk resolves it for you - asynchronously, via the ORM - before your handler runs. A missing row returns a `404` automatically, without you writing a null check:

```php
use App\Entities\User;

$app->get('/users/{user}/bound', function (ServerRequestInterface $request, User $user) {
    return Response::json(['id' => $user->getId(), 'name' => $user->getName()]);
});
```

This only fires for classes that implement `EntityInterface` - a route parameter typed with any other class is left as the raw string segment, so it's safe to mix with normal scalar-typed parameters.

## FormRequest validation

If a handler's **first** parameter type-hints a class extending `Trunk\Validation\FormRequest`, Trunk builds and validates it before invoking your handler. A validation failure short-circuits to a `422` with field errors - your handler never runs. See [Validation](/guide/validation) for the rule syntax.

```php
use App\Requests\CreateUserRequest;

$app->post('/users', function (CreateUserRequest $request) {
    $data = $request->validated();
    // ...
});
```

## Per-route middleware

Pass an array of middleware as the last argument to apply it to that route only (as opposed to the globally-registered middleware described in [Middleware](/guide/middleware)):

```php
use App\Middleware\AuthMiddleware;

$app->post('/users', [UserController::class, 'create'], [AuthMiddleware::class]);
```

## 404s and errors

Unmatched routes return a `404` JSON response automatically. Uncaught exceptions from inside a handler (or from route model binding / FormRequest validation, covered above) are converted to a JSON error response rather than crashing the process - the event loop keeps serving other in-flight requests either way.

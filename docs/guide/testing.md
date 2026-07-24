# Testing

Trunk uses PHPUnit. Since everything async returns a `PromiseInterface`, tests use [`react/async`](https://github.com/reactphp/async)'s `await()` to resolve a promise synchronously inside a test method.

## Setup

Both the framework and a skeleton app carry their own `phpunit.xml.dist` and dev dependencies:

```bash
composer require --dev phpunit/phpunit react/async
composer test   # or: vendor/bin/phpunit
```

## Testing pure logic

Anything that doesn't touch I/O - `Validator`, the `Grammar` classes, `Config\Repository` - needs no mocking at all:

```php
use Trunk\Validation\Validator;

public function testRequiredFieldFails(): void
{
    $validator = Validator::make([], ['name' => 'required']);

    $this->assertTrue($validator->fails());
}
```

## Testing a `FormRequest`

Build a real PSR-7 request with `React\Http\Message\ServerRequest` and validate it directly - no router or HTTP server needed:

```php
use React\Http\Message\ServerRequest;
use Trunk\Validation\Exception\ValidationException;

public function testMissingEmailFails(): void
{
    $request = (new ServerRequest('POST', '/users'))->withParsedBody(['name' => 'Alice']);
    $formRequest = new CreateUserRequest($request);

    $this->expectException(ValidationException::class);
    $formRequest->validate();
}
```

## Testing a controller with a real dependency

If a dependency is pure and deterministic (like `JwtTokenService`), it's often more useful to use the real thing than to mock it away:

```php
$tokens = new JwtTokenService(secret: str_repeat('a', 32));
$controller = new AuthController($tokens);

$response = $controller->login($request);
$claims = $tokens->verify(json_decode((string) $response->getBody(), true)['token']);

$this->assertSame('demo@trunk.dev', $claims['sub']);
```

## Testing a controller with async dependencies mocked

`EntityManager`, `Repository`, and `Dispatcher` are all plain classes, so PHPUnit's `createMock()` works directly - no test doubles or interfaces to hand-write:

```php
use function React\Async\await;
use function React\Promise\resolve;

public function testCreateDispatchesUserRegistered(): void
{
    $repository = $this->createMock(Repository::class);
    $repository->method('persist')->willReturn(resolve($persistedUser));

    $entityManager = $this->createMock(EntityManager::class);
    $entityManager->method('getRepository')->willReturn($repository);

    $dispatcher = $this->createMock(Dispatcher::class);
    $dispatcher->expects($this->once())->method('dispatchAsync')
        ->with($this->isInstanceOf(UserRegistered::class));

    $controller = new UserController($logger, $entityManager, $dispatcher);

    $response = await($controller->create($formRequest));

    $this->assertSame(201, $response->getStatusCode());
}
```

This is the pattern to reach for whenever a controller's logic is worth testing on its own - no live database, no running server, no event loop management beyond `await()`.

## Testing the `Router` itself

The framework's own test suite (`trunk/tests/Http/RouterTest.php`) is the reference for testing route matching, `FormRequest` short-circuiting, route model binding, and per-route middleware in isolation - useful if you're extending routing behavior yourself.

## What isn't unit-tested

Driver-level I/O (`MysqlDriver`, `PostgresDriver`) needs a real database and is verified by running the app against one directly, not mocked in the suite - mocking the wire protocol wouldn't catch the class of bugs that matter there (dialect differences, connection handling). If you add integration coverage for your own app's database code, keep it in a separate suite/CI job from the fast, mock-based unit tests above.

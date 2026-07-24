# Events

Trunk's event system is object-typed (dispatch a class instance, not a string name) and async-aware.

## Defining an event

```php
namespace App\Events;

class UserRegistered
{
    public function __construct(public readonly \App\Entities\User $user) {}
}
```

## Listening

```php
namespace App\Listeners;

use App\Events\UserRegistered;
use Trunk\Log\Logger;

class LogUserRegistrationListener
{
    public function __construct(private readonly Logger $logger) {}

    public function __invoke(UserRegistered $event): void
    {
        $this->logger->info('User registered: {id}', ['id' => $event->user->getId()]);
    }
}
```

Register it in `config/events.php` - listeners are resolved (and autowired) through the container automatically at boot:

```php
use App\Events\UserRegistered;
use App\Listeners\LogUserRegistrationListener;

return [
    UserRegistered::class => [
        LogUserRegistrationListener::class,
    ],
];
```

## Dispatching

```php
use Trunk\Event\Dispatcher;

class UserController
{
    public function __construct(private readonly Dispatcher $events) {}

    public function create(CreateUserRequest $request): PromiseInterface
    {
        // ... persist the user ...

        $this->events->dispatchAsync(new UserRegistered($user));

        return Response::json([/* ... */], 201);
    }
}
```

- **`dispatch($event)`** returns a `PromiseInterface` that resolves once every listener (including any that return their own promises) has settled - use this when the caller needs to know listeners finished.
- **`dispatchAsync($event)`** fires listeners without making the caller wait. Use this for side effects (emails, webhooks, analytics) that shouldn't delay the HTTP response - which is the common case, since Trunk is a single-event-loop framework and a slow listener would otherwise stall every other in-flight request.

A listener that throws doesn't crash the dispatch or affect other listeners - the failure is logged and the dispatch promise still resolves.

## Framework lifecycle events

Two events fire automatically around every request, giving you a hook point without touching core code or writing middleware:

- `Trunk\Event\Events\RequestReceived` - `$request`
- `Trunk\Event\Events\ResponseSent` - `$request`, `$response`, `$durationMs`

Listen for them the same way as any other event.

# Events

Trunk's event system is object-typed (dispatch a class instance, not a string name) and async-aware. This isn't wired into the skeleton by default - the example below is standalone, adapt the names to your own app.

## Defining an event

```php
namespace App\Events;

use App\Entities\Order;

class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}
```

## Listening

```php
namespace App\Listeners;

use App\Events\OrderPlaced;
use Trunk\Log\Logger;

class LogOrderPlacedListener
{
    public function __construct(private readonly Logger $logger) {}

    public function __invoke(OrderPlaced $event): void
    {
        $this->logger->info('Order placed: {id}', ['id' => $event->order->getId()]);
    }
}
```

Register it in `config/events.php` - listeners are resolved (and autowired) through the container automatically at boot:

```php
use App\Events\OrderPlaced;
use App\Listeners\LogOrderPlacedListener;

return [
    OrderPlaced::class => [
        LogOrderPlacedListener::class,
    ],
];
```

## Dispatching

```php
use Trunk\Event\Dispatcher;

class OrderController
{
    public function __construct(private readonly Dispatcher $events) {}

    public function create(CreateOrderRequest $request): PromiseInterface
    {
        // ... persist the order ...

        $this->events->dispatchAsync(new OrderPlaced($order));

        return Response::json([/* ... */], 201);
    }
}
```

- **`dispatch($event)`** returns a `PromiseInterface` that resolves once every listener (including any that return their own promises) has settled - use this when the caller needs to know listeners finished.
- **`dispatchAsync($event)`** fires listeners without making the caller wait. Use this for side effects (emails, webhooks, analytics) that shouldn't delay the HTTP response - which is the common case, since Trunk is a single-event-loop framework and a slow listener would otherwise stall every other in-flight request. It's implemented as a call to `dispatch()` whose returned promise the caller simply doesn't await.

## Listener failures are isolated

A listener that throws (synchronously, or via a rejected promise it returns) doesn't crash the dispatch or affect other listeners registered for the same event - the failure is logged (`Event listener failed for {event}: {error}`) via the container's bound `Psr\Log\LoggerInterface`, and the dispatch promise still resolves rather than rejecting. This means a broken listener degrades gracefully (you lose that side effect and see it in your logs) instead of taking down the request that triggered it.

## Framework lifecycle events

Two events fire automatically around every request, giving you a hook point without touching core code or writing middleware:

- `Trunk\Event\Events\RequestReceived` - `$request`
- `Trunk\Event\Events\ResponseSent` - `$request`, `$response`, `$durationMs`

Listen for them the same way as any other event.

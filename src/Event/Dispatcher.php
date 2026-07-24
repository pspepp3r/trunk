<?php

namespace Trunk\Event;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function get_class;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class Dispatcher
{
    private EventEmitter $emitter;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->emitter = new EventEmitter();
    }

    /**
     * Register a listener for an event class. The listener receives the event object
     * and may optionally return a PromiseInterface for async work.
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->emitter->on($eventClass, $listener);
    }

    /**
     * Dispatch an event to all its listeners, awaiting any promises they return.
     * Listener failures are logged and do not reject the returned promise or affect
     * other listeners; the promise always resolves with the event object.
     */
    public function dispatch(object $event): PromiseInterface
    {
        $listeners = $this->emitter->listeners(get_class($event));
        $promises = [];

        foreach ($listeners as $listener) {
            try {
                $result = $listener($event);
                $promises[] = $result instanceof PromiseInterface ? $result : resolve($result);
            } catch (\Throwable $e) {
                $promises[] = reject($e);
            }
        }

        return all($promises)->then(
            fn() => $event,
            function (\Throwable $e) use ($event) {
                $this->logger->error('Event listener failed for {event}: {error}', [
                    'event' => get_class($event),
                    'error' => $e->getMessage(),
                ]);
                return $event;
            }
        );
    }

    /**
     * Dispatch an event without making the caller wait for listeners to finish.
     * Use this for side effects (emails, webhooks, analytics) that shouldn't delay
     * the response. Listener failures are still logged.
     */
    public function dispatchAsync(object $event): void
    {
        $this->dispatch($event);
    }
}

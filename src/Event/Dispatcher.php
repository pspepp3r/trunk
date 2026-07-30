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

    public function listen(string $eventClass, callable $listener): void
    {
        $this->emitter->on($eventClass, $listener);
    }

    /** Listener failures are logged, not thrown - see the Events guide's dispatch semantics section. */
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

    /** Fire-and-forget dispatch for side effects that shouldn't delay the response - see the Events guide. */
    public function dispatchAsync(object $event): void
    {
        $this->dispatch($event);
    }
}

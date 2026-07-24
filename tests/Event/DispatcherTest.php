<?php

namespace Trunk\Tests\Event;

use PHPUnit\Framework\TestCase;
use Trunk\Event\Dispatcher;
use Trunk\Tests\Fixtures\SpyLogger;

use function React\Async\await;
use function React\Promise\resolve;

class TestEvent
{
    public function __construct(public readonly string $payload)
    {
    }
}

class DispatcherTest extends TestCase
{
    public function testDispatchInvokesRegisteredListenerWithTheEvent(): void
    {
        $dispatcher = new Dispatcher(new SpyLogger());
        $received = null;

        $dispatcher->listen(TestEvent::class, function (TestEvent $event) use (&$received) {
            $received = $event;
        });

        $result = await($dispatcher->dispatch(new TestEvent('hello')));

        $this->assertInstanceOf(TestEvent::class, $received);
        $this->assertSame('hello', $received->payload);
        $this->assertSame('hello', $result->payload);
    }

    public function testDispatchInvokesAllListenersForAnEvent(): void
    {
        $dispatcher = new Dispatcher(new SpyLogger());
        $calls = [];

        $dispatcher->listen(TestEvent::class, function () use (&$calls) { $calls[] = 'first'; });
        $dispatcher->listen(TestEvent::class, function () use (&$calls) { $calls[] = 'second'; });

        await($dispatcher->dispatch(new TestEvent('x')));

        $this->assertSame(['first', 'second'], $calls);
    }

    public function testDispatchDoesNotInvokeListenersRegisteredForOtherEvents(): void
    {
        $dispatcher = new Dispatcher(new SpyLogger());
        $called = false;

        $dispatcher->listen('SomeOtherEvent', function () use (&$called) { $called = true; });

        await($dispatcher->dispatch(new TestEvent('x')));

        $this->assertFalse($called);
    }

    public function testDispatchAwaitsPromiseReturningListeners(): void
    {
        $dispatcher = new Dispatcher(new SpyLogger());
        $settled = false;

        $dispatcher->listen(TestEvent::class, function () use (&$settled) {
            return resolve(null)->then(function () use (&$settled) {
                $settled = true;
            });
        });

        await($dispatcher->dispatch(new TestEvent('x')));

        $this->assertTrue($settled);
    }

    public function testListenerExceptionIsCaughtLoggedAndDoesNotRejectDispatch(): void
    {
        $logger = new SpyLogger();
        $dispatcher = new Dispatcher($logger);

        $dispatcher->listen(TestEvent::class, function () {
            throw new \RuntimeException('listener exploded');
        });

        $result = await($dispatcher->dispatch(new TestEvent('x')));

        $this->assertSame('x', $result->payload, 'dispatch should still resolve with the event');
        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertStringContainsString('listener exploded', $logger->records[0]['context']['error']);
    }

    public function testDispatchAsyncStillInvokesListenersWithoutReturningAPromise(): void
    {
        $dispatcher = new Dispatcher(new SpyLogger());
        $received = null;

        $dispatcher->listen(TestEvent::class, function (TestEvent $event) use (&$received) {
            $received = $event;
        });

        $dispatcher->dispatchAsync(new TestEvent('fire-and-forget'));

        $this->assertInstanceOf(TestEvent::class, $received);
        $this->assertSame('fire-and-forget', $received->payload);
    }
}

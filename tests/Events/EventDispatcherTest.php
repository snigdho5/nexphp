<?php

declare(strict_types=1);

namespace Nex\Tests\Events;

use Nex\Events\Contracts\ListenerInterface;
use Nex\Events\Contracts\SubscriberInterface;
use Nex\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// ---- Test event classes ----

final class UserRegistered
{
    public function __construct(public readonly string $email) {}
}

final class UserDeleted
{
    public function __construct(public readonly int $id) {}
}

final class MutableEvent
{
    public int $value = 0;
}

// ---- Test listener ----

final class LoggingListener implements ListenerInterface
{
    public array $log = [];

    public function handle(object $event): void
    {
        $this->log[] = $event;
    }
}

// ---- Subscriber ----

final class UserEventSubscriber implements SubscriberInterface
{
    public array $handledRegistered = [];
    public array $handledDeleted = [];

    public function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => ['onRegistered', 10],
            UserDeleted::class    => 'onDeleted',
        ];
    }

    public function onRegistered(object $event): void
    {
        $this->handledRegistered[] = $event;
    }

    public function onDeleted(object $event): void
    {
        $this->handledDeleted[] = $event;
    }
}

// ---- Tests ----

final class EventDispatcherTest extends TestCase
{
    public function test_dispatch_calls_registered_listener(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new LoggingListener();
        $dispatcher->listen(UserRegistered::class, $listener);

        $event = new UserRegistered('jane@example.com');
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertCount(1, $listener->log);
        $this->assertSame($event, $listener->log[0]);
    }

    public function test_dispatch_calls_callable_listener(): void
    {
        $dispatcher = new EventDispatcher();
        $received = [];

        $dispatcher->listen(UserRegistered::class, function (object $event) use (&$received) {
            $received[] = $event;
        });

        $event = new UserRegistered('jane@example.com');
        $dispatcher->dispatch($event);

        $this->assertCount(1, $received);
    }

    public function test_dispatch_calls_multiple_listeners_in_priority_order(): void
    {
        $dispatcher = new EventDispatcher();
        $order = [];

        $dispatcher->listen(MutableEvent::class, function () use (&$order) { $order[] = 'low'; }, priority: 1);
        $dispatcher->listen(MutableEvent::class, function () use (&$order) { $order[] = 'high'; }, priority: 10);
        $dispatcher->listen(MutableEvent::class, function () use (&$order) { $order[] = 'medium'; }, priority: 5);

        $dispatcher->dispatch(new MutableEvent());

        $this->assertSame(['high', 'medium', 'low'], $order);
    }

    public function test_dispatch_no_listeners_is_noop(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new UserRegistered('nobody@example.com');
        $result = $dispatcher->dispatch($event);
        $this->assertSame($event, $result);
    }

    public function test_listener_exception_stops_dispatch_by_default(): void
    {
        $dispatcher = new EventDispatcher(stopOnError: true);
        $callCount = 0;

        $dispatcher->listen(UserRegistered::class, function () {
            throw new RuntimeException("listener error");
        });
        $dispatcher->listen(UserRegistered::class, function () use (&$callCount) {
            $callCount++;
        });

        $this->expectException(RuntimeException::class);
        $dispatcher->dispatch(new UserRegistered('test@example.com'));

        // Second listener should NOT be called (stopped)
    }

    public function test_listener_exception_does_not_stop_dispatch_when_configured(): void
    {
        $dispatcher = new EventDispatcher(stopOnError: false);
        $callCount = 0;

        $dispatcher->listen(UserRegistered::class, function () {
            throw new RuntimeException("listener error");
        });
        $dispatcher->listen(UserRegistered::class, function () use (&$callCount) {
            $callCount++;
        });

        // Should NOT throw, should call both listeners
        $dispatcher->dispatch(new UserRegistered('test@example.com'));
        $this->assertSame(1, $callCount);
    }

    public function test_subscriber_registers_multiple_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new UserEventSubscriber();
        $dispatcher->subscribe($subscriber);

        $reg = new UserRegistered('alice@example.com');
        $del = new UserDeleted(42);

        $dispatcher->dispatch($reg);
        $dispatcher->dispatch($del);

        $this->assertCount(1, $subscriber->handledRegistered);
        $this->assertSame($reg, $subscriber->handledRegistered[0]);
        $this->assertCount(1, $subscriber->handledDeleted);
        $this->assertSame($del, $subscriber->handledDeleted[0]);
    }

    public function test_get_listeners_for_event(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new LoggingListener();
        $dispatcher->listen(UserRegistered::class, $listener);

        $listeners = $dispatcher->getListenersForEvent(UserRegistered::class);
        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]);
    }

    public function test_listener_can_mutate_event(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(MutableEvent::class, function (MutableEvent $event) {
            $event->value = 42;
        });

        $event = new MutableEvent();
        $dispatcher->dispatch($event);
        $this->assertSame(42, $event->value);
    }
}

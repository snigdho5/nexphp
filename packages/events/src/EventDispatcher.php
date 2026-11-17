<?php

declare(strict_types=1);

namespace Nex\Events;

use Nex\Events\Contracts\EventDispatcherInterface;
use Nex\Events\Contracts\ListenerInterface;
use Nex\Events\Contracts\SubscriberInterface;
use Throwable;

/**
 * Synchronous event dispatcher.
 *
 * Guarantees:
 * - Listeners are called in priority order (highest first, same priority = insertion order).
 * - By default, a listener exception stops dispatch and re-throws. Set $stopOnError = false to continue.
 * - Dispatch is always synchronous. For async delivery, use a queued listener adapter.
 * - Events are not serializable unless you make them so intentionally.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<class-string, list<array{callable|ListenerInterface, int}>>
     */
    private array $listeners = [];

    /**
     * @var array<class-string, bool> whether the listeners for a given event are sorted
     */
    private array $sorted = [];

    public function __construct(
        private readonly bool $stopOnError = true,
    ) {}

    /**
     * Register a callable or ListenerInterface for a given event class.
     *
     * @param class-string $eventClass
     * @param callable|ListenerInterface $listener
     */
    public function listen(string $eventClass, callable|ListenerInterface $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [$listener, $priority];
        $this->sorted[$eventClass] = false;
    }

    /**
     * Register a subscriber that maps multiple events to methods on itself.
     */
    public function subscribe(SubscriberInterface $subscriber): void
    {
        foreach ($subscriber->getSubscribedEvents() as $eventClass => $definition) {
            if (is_string($definition)) {
                $method = $definition;
                $priority = 0;
            } else {
                $method = $definition[0];
                $priority = $definition[1] ?? 0;
            }

            $this->listen(
                $eventClass,
                fn (object $event) => $subscriber->$method($event),
                $priority,
            );
        }
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        $eventClass = $event::class;

        // Sort listeners by priority (descending) on first use
        if (isset($this->listeners[$eventClass]) && !($this->sorted[$eventClass] ?? true)) {
            usort(
                $this->listeners[$eventClass],
                fn ($a, $b) => $b[1] <=> $a[1],
            );
            $this->sorted[$eventClass] = true;
        }

        foreach ($this->listeners[$eventClass] ?? [] as [$listener, $priority]) {
            try {
                if ($listener instanceof ListenerInterface) {
                    $listener->handle($event);
                } else {
                    ($listener)($event);
                }
            } catch (Throwable $e) {
                if ($this->stopOnError) {
                    throw $e;
                }
                // Continue to next listener if stopOnError = false
            }
        }

        return $event;
    }

    /**
     * @return list<callable|ListenerInterface>
     */
    public function getListenersForEvent(string $eventClass): array
    {
        return array_column($this->listeners[$eventClass] ?? [], 0);
    }
}

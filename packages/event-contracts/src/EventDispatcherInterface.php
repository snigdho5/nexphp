<?php

declare(strict_types=1);

namespace Nex\Events\Contracts;

/**
 * Synchronous event dispatcher contract.
 *
 * Dispatch an event object and get it back (possibly mutated by listeners).
 * Dispatch is always synchronous. For durable/async delivery, push to a queue.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object;
}

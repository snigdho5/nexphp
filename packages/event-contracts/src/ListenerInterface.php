<?php

declare(strict_types=1);

namespace Nex\Events\Contracts;

/**
 * A single event listener.
 */
interface ListenerInterface
{
    public function handle(object $event): void;
}

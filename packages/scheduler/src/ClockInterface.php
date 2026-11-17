<?php

declare(strict_types=1);

namespace Nex\Scheduler;

use DateTimeImmutable;

/**
 * A clock abstraction for deterministic scheduler testing.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

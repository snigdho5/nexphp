<?php

declare(strict_types=1);

namespace Nex\Scheduler;

use DateTimeImmutable;

/**
 * Real-time clock.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

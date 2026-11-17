<?php

declare(strict_types=1);

namespace Nex\Scheduler;

use DateTimeImmutable;

/**
 * Frozen clock for testing.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function advance(int $seconds): void
    {
        $this->time = $this->time->modify("+{$seconds} seconds");
    }

    public function setTime(string $datetime): void
    {
        $this->time = new DateTimeImmutable($datetime);
    }
}

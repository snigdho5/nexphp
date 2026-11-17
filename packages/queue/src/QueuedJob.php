<?php

declare(strict_types=1);

namespace Nex\Queue;

use Nex\Queue\Contracts\QueuedJobInterface;

/**
 * A job envelope as retrieved from the database queue.
 */
final class QueuedJob implements QueuedJobInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $queue,
        private readonly int $attempts,
        private readonly array $payload,
        private readonly int $reservedAt,
        private readonly int $maxAttempts,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getJobClass(): string
    {
        return $this->payload['job'] ?? '';
    }

    public function getReservedAt(): int
    {
        return $this->reservedAt;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}

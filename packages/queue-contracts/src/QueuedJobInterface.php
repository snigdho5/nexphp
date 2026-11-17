<?php

declare(strict_types=1);

namespace Nex\Queue\Contracts;

/**
 * A job envelope retrieved from the queue.
 */
interface QueuedJobInterface
{
    /** Unique ID assigned by the queue adapter. */
    public function getId(): string;

    /** Name of the queue this job came from. */
    public function getQueue(): string;

    /** Number of times this job has been attempted (1 on first pop). */
    public function getAttempts(): int;

    /** Raw deserialized payload. */
    public function getPayload(): array;

    /** The resolved job class name. */
    public function getJobClass(): string;

    /** UNIX timestamp when the job was reserved (popped). */
    public function getReservedAt(): int;

    /** Maximum attempts before moving to failed store (0 = unlimited). */
    public function getMaxAttempts(): int;
}

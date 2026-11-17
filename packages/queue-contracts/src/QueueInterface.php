<?php

declare(strict_types=1);

namespace Nex\Queue\Contracts;

/**
 * Queue adapter contract.
 *
 * Delivery guarantees:
 * - pop() reserves a job atomically; it must NOT be visible to other workers until released or acknowledged.
 * - acknowledge() removes the job permanently after successful processing.
 * - release() makes the job available again (after a delay) for retry.
 * - Worker crashes must not silently discard reserved jobs — adapters should use a visibility timeout or lease.
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue. Returns the job ID.
     *
     * Supported $options:
     *   - queue    (string)  Target queue name. Default: 'default'.
     *   - delay    (int)     Seconds before job becomes available. Default: 0.
     *   - attempts (int)     Max attempts before failing. Default: 3.
     */
    public function push(object $job, array $options = []): string;

    /**
     * Reserve the next available job from the queue.
     * Returns null if no job is available.
     */
    public function pop(?string $queue = null): ?QueuedJobInterface;

    /**
     * Acknowledge a successfully processed job (removes it from the queue).
     */
    public function acknowledge(QueuedJobInterface $job): void;

    /**
     * Release a job back to the queue for retry.
     * $delaySeconds controls the visibility delay before it becomes available again.
     */
    public function release(QueuedJobInterface $job, int $delaySeconds = 0): void;

    /**
     * Move a job to the failed-job store (called after max attempts).
     */
    public function fail(QueuedJobInterface $job, string $exceptionMessage): void;
}

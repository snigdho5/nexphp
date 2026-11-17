<?php

declare(strict_types=1);

namespace Nex\Queue;

use Nex\Queue\Contracts\QueueInterface;
use Throwable;

/**
 * Processes jobs from the queue with retry and failure semantics.
 *
 * Usage:
 *   $worker = new QueueWorker($queue, $container);
 *   $worker->runOnce('default');  // process one job
 *   $worker->run('default');       // loop until $worker->stop()
 */
final class QueueWorker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly ?\Nex\Container\Container $container = null,
        private readonly int $sleep = 1,
        private readonly int $backoffSeconds = 5,
    ) {}

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Process one job and return true if a job was processed, false if the queue was empty.
     */
    public function runOnce(?string $queue = null): bool
    {
        $job = $this->queue->pop($queue);

        if ($job === null) {
            return false;
        }

        $jobClass = $job->getJobClass();

        if (!class_exists($jobClass)) {
            $this->queue->fail($job, "Job class [$jobClass] does not exist.");
            return true;
        }

        try {
            // Reconstruct the job instance from its serialized payload data
            $ref = new \ReflectionClass($jobClass);
            $data = $job->getPayload()['data'] ?? [];
            $instance = $ref->newInstanceWithoutConstructor();

            foreach ($data as $property => $value) {
                if ($ref->hasProperty($property)) {
                    $prop = $ref->getProperty($property);
                    $prop->setValue($instance, $value);
                }
            }

            // Call handle() with DI if container is available
            if ($this->container !== null) {
                $this->container->call([$instance, 'handle']);
            } else {
                $instance->handle();
            }

            $this->queue->acknowledge($job);
        } catch (Throwable $e) {
            $attempts = $job->getAttempts();
            $maxAttempts = $job->getMaxAttempts();

            if ($maxAttempts > 0 && $attempts >= $maxAttempts) {
                $this->queue->fail($job, $e->getMessage());
            } else {
                // Exponential backoff: 5, 10, 20, ...
                $delay = $this->backoffSeconds * (2 ** ($attempts - 1));
                $this->queue->release($job, min($delay, 300));
            }
        }

        return true;
    }

    /**
     * Run the worker loop until stop() is called.
     */
    public function run(?string $queue = null): void
    {
        while (!$this->shouldStop) {
            $processed = $this->runOnce($queue);

            if (!$processed) {
                sleep($this->sleep);
            }
        }
    }
}

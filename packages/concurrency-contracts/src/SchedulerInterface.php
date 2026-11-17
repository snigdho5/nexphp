<?php

declare(strict_types=1);

namespace Nex\Concurrency\Contracts;

interface SchedulerInterface
{
    public function spawn(callable $callback): TaskInterface;

    public function run(callable $callback): mixed;

    /**
     * @param iterable<callable|TaskInterface> $tasks
     * @return array<array-key, mixed>
     */
    public function all(iterable $tasks): array;

    /**
     * @param iterable<callable|TaskInterface> $tasks
     */
    public function race(iterable $tasks): mixed;

    public function withTimeout(float $seconds, callable $callback): mixed;
}

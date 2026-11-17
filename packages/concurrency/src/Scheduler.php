<?php

declare(strict_types=1);

namespace Nex\Concurrency;

use Fiber;
use Nex\Concurrency\Contracts\SchedulerInterface;
use Nex\Concurrency\Contracts\TaskInterface;
use Throwable;

class Scheduler implements SchedulerInterface
{
    public function spawn(callable $callback): TaskInterface
    {
        return new Task($callback);
    }

    public function run(callable $callback): mixed
    {
        $task = $this->spawn($callback);
        $fiber = $task->getFiber();
        $fiber->start();

        return $task->result();
    }

    /**
     * Cooperatively execute tasks concurrently using Fibers and return all results.
     *
     * @param iterable<callable|TaskInterface> $tasks
     * @return array<array-key, mixed>
     */
    public function all(iterable $tasks): array
    {
        /** @var array<array-key, Task> $taskList */
        $taskList = [];

        foreach ($tasks as $key => $item) {
            $taskList[$key] = $item instanceof Task ? $item : new Task($item);
        }

        $active = $taskList;

        while (!empty($active)) {
            foreach ($active as $key => $task) {
                if ($task->isCancelled()) {
                    unset($active[$key]);
                    continue;
                }

                $fiber = $task->getFiber();

                try {
                    if (!$fiber->isStarted()) {
                        $fiber->start();
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (Throwable) {
                    // Handled inside Task
                }

                if ($task->isComplete() || $task->isFailed() || $fiber->isTerminated()) {
                    unset($active[$key]);
                }
            }

            if (!empty($active)) {
                // Yield CPU micro-tick to avoid busy spin
                usleep(100);
            }
        }

        $results = [];
        foreach ($taskList as $key => $task) {
            if ($task->isFailed()) {
                throw $task->error();
            }
            $results[$key] = $task->result();
        }

        return $results;
    }

    public function race(iterable $tasks): mixed
    {
        /** @var array<array-key, Task> $taskList */
        $taskList = [];

        foreach ($tasks as $key => $item) {
            $taskList[$key] = $item instanceof Task ? $item : new Task($item);
        }

        $active = $taskList;

        while (!empty($active)) {
            foreach ($active as $key => $task) {
                $fiber = $task->getFiber();

                try {
                    if (!$fiber->isStarted()) {
                        $fiber->start();
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (Throwable) {
                    // Handled
                }

                if ($task->isComplete()) {
                    // Cancel all remaining tasks
                    foreach ($taskList as $other) {
                        if ($other !== $task) {
                            $other->cancel();
                        }
                    }
                    return $task->result();
                }

                if ($task->isFailed() || $fiber->isTerminated()) {
                    unset($active[$key]);
                }
            }

            if (!empty($active)) {
                usleep(100);
            }
        }

        throw new \RuntimeException('All tasks in race failed without a winner.');
    }

    public function withTimeout(float $seconds, callable $callback): mixed
    {
        $task = new Task($callback);
        $fiber = $task->getFiber();
        $start = microtime(true);

        while (!$task->isComplete()) {
            try {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            } catch (Throwable $e) {
                // Handled in Task
            }

            if ((microtime(true) - $start) >= $seconds) {
                $task->cancel();
                throw new TimeoutException($seconds);
            }

            if ($task->isFailed()) {
                throw $task->error();
            }

            if (!$task->isComplete()) {
                usleep(100);
            }
        }

        return $task->result();
    }

    /**
     * Yield execution from the current Fiber back to the scheduler.
     */
    public static function suspend(): mixed
    {
        if (Fiber::getCurrent() !== null) {
            return Fiber::suspend();
        }

        return null;
    }
}

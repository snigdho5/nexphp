<?php

declare(strict_types=1);

namespace Nex\Scheduler;

use DateTimeImmutable;

/**
 * The task scheduler.
 *
 * Usage:
 *   $schedule = new Schedule($clock);
 *   $schedule->command('reports:daily')->dailyAt('02:00')->timezone('Asia/Kolkata')->withoutOverlapping();
 *   $schedule->call(fn() => cleanTempFiles(), 'clean-temp')->daily();
 *   $schedule->tick(); // call every minute from queue:run or CLI
 */
final class Schedule
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly string $lockDir = '',
    ) {}

    public function command(string $command): ScheduledTask
    {
        $task = ScheduledTask::command($command, $this->getLockDir());
        $this->tasks[] = $task;
        return $task;
    }

    public function call(callable $callback, string $description = 'closure'): ScheduledTask
    {
        $task = ScheduledTask::call($callback, $description, $this->getLockDir());
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Execute all tasks that are due right now.
     * Returns the number of tasks executed.
     */
    public function tick(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $task->run();
                $count++;
            }
        }

        return $count;
    }

    /** @return list<ScheduledTask> */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /** @return list<ScheduledTask> */
    public function getDueTasks(?DateTimeImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();
        return array_values(array_filter(
            $this->tasks,
            fn (ScheduledTask $task) => $task->isDue($now),
        ));
    }

    private function getLockDir(): string
    {
        return $this->lockDir !== '' ? $this->lockDir : sys_get_temp_dir();
    }
}

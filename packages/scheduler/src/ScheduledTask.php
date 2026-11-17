<?php

declare(strict_types=1);

namespace Nex\Scheduler;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A single scheduled task.
 *
 * Example:
 *   $task = ScheduledTask::command('reports:daily')->dailyAt('02:00')->timezone('Asia/Kolkata')->withoutOverlapping();
 */
final class ScheduledTask
{
    private string $cronExpression = '* * * * *';
    private ?DateTimeZone $timezone = null;
    private bool $withoutOverlapping = false;
    private string $lockPath;
    /** @var callable|null */
    private $callback = null;
    private string $command = '';
    private string $description = '';

    private function __construct(private readonly string $lockDir)
    {
        $dir = $this->lockDir !== '' ? $this->lockDir : sys_get_temp_dir();
        $this->lockPath = $dir . '/nex_scheduler_' . md5(spl_object_hash($this)) . '.lock';
    }

    public static function command(string $command, string $lockDir = ''): static
    {
        $task = new static($lockDir);
        $task->command = $command;
        $task->description = "command:{$command}";
        return $task;
    }

    public static function call(callable $callback, string $description = 'closure', string $lockDir = ''): static
    {
        $task = new static($lockDir);
        $task->callback = $callback;
        $task->description = $description;
        return $task;
    }

    public function cron(string $expression): static
    {
        $this->cronExpression = $expression;
        $this->lockPath = $this->lockDir . '/nex_scheduler_' . md5($this->description . $expression) . '.lock';
        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = explode(':', $time, 2) + [0, '00'];
        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): static
    {
        [$hour, $minute] = explode(':', $time, 2) + [0, '00'];
        return $this->cron("{$minute} {$hour} * * {$dayOfWeek}");
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function timezone(string $tz): static
    {
        $this->timezone = new DateTimeZone($tz);
        return $this;
    }

    public function withoutOverlapping(string $lockDir = ''): static
    {
        $this->withoutOverlapping = true;
        if ($lockDir !== '') {
            $this->lockPath = $lockDir . '/nex_scheduler_' . md5($this->description) . '.lock';
        }
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isDue(DateTimeImmutable $now): bool
    {
        $checkTime = $this->timezone !== null
            ? $now->setTimezone($this->timezone)
            : $now;

        return $this->matchesCron($checkTime);
    }

    public function run(): void
    {
        if ($this->withoutOverlapping && $this->isLocked()) {
            return;
        }

        if ($this->withoutOverlapping) {
            $this->acquireLock();
        }

        try {
            if ($this->callback !== null) {
                ($this->callback)();
            } elseif ($this->command !== '') {
                // Run as shell command
                passthru($this->command);
            }
        } finally {
            if ($this->withoutOverlapping) {
                $this->releaseLock();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Cron matching
    // -------------------------------------------------------------------------

    private function matchesCron(DateTimeImmutable $time): bool
    {
        $parts = explode(' ', $this->cronExpression);
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->matchField($minute, (int) $time->format('i'))
            && $this->matchField($hour, (int) $time->format('G'))
            && $this->matchField($day, (int) $time->format('j'))
            && $this->matchField($month, (int) $time->format('n'))
            && $this->matchField($weekday, (int) $time->format('w'));
    }

    private function matchField(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // Step values: */5
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);
            return $step > 0 && $value % $step === 0;
        }

        // Ranges: 1-5
        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field, 2);
            return $value >= (int) $from && $value <= (int) $to;
        }

        // Lists: 1,3,5
        if (str_contains($field, ',')) {
            return in_array($value, array_map('intval', explode(',', $field)), true);
        }

        return (int) $field === $value;
    }

    // -------------------------------------------------------------------------
    // Lock management (file-based, single-server)
    // -------------------------------------------------------------------------

    private function isLocked(): bool
    {
        if (!file_exists($this->lockPath)) {
            return false;
        }
        // Check if lock is stale (> 10 minutes)
        return (time() - (int) filemtime($this->lockPath)) < 600;
    }

    private function acquireLock(): void
    {
        file_put_contents($this->lockPath, (string) getmypid());
    }

    private function releaseLock(): void
    {
        if (file_exists($this->lockPath)) {
            unlink($this->lockPath);
        }
    }
}

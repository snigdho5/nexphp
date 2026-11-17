<?php

declare(strict_types=1);

namespace Nex\Tests\Scheduler;

use DateTimeImmutable;
use Nex\Scheduler\FrozenClock;
use Nex\Scheduler\Schedule;
use Nex\Scheduler\ScheduledTask;
use Nex\Scheduler\SystemClock;
use PHPUnit\Framework\TestCase;

final class SchedulerTest extends TestCase
{
    private string $lockDir;

    protected function setUp(): void
    {
        $this->lockDir = sys_get_temp_dir() . '/nex_sched_test_' . uniqid();
        mkdir($this->lockDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup lock files
        foreach (glob($this->lockDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->lockDir);
    }

    public function test_every_minute_task_is_due(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 10:30:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'test')->everyMinute();

        $count = $schedule->tick();
        $this->assertTrue($executed);
        $this->assertSame(1, $count);
    }

    public function test_daily_at_task_is_due_at_correct_time(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 02:00:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'daily-test')->dailyAt('02:00');

        $count = $schedule->tick();
        $this->assertTrue($executed);
        $this->assertSame(1, $count);
    }

    public function test_daily_at_task_is_not_due_at_wrong_time(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 03:30:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'daily-test')->dailyAt('02:00');

        $count = $schedule->tick();
        $this->assertFalse($executed);
        $this->assertSame(0, $count);
    }

    public function test_every_five_minutes_is_due(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 10:15:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'every5')->everyFiveMinutes();

        $schedule->tick();
        $this->assertTrue($executed);
    }

    public function test_every_five_minutes_not_due_at_non_multiple(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 10:17:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'every5')->everyFiveMinutes();

        $schedule->tick();
        $this->assertFalse($executed);
    }

    public function test_multiple_tasks_can_be_scheduled(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 00:00:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $count = 0;

        $schedule->call(function () use (&$count) { $count++; }, 'task1')->everyMinute();
        $schedule->call(function () use (&$count) { $count++; }, 'task2')->everyMinute();
        $schedule->call(function () use (&$count) { $count++; }, 'task3')->dailyAt('12:00');

        $executed = $schedule->tick();
        $this->assertSame(2, $executed);
        $this->assertSame(2, $count);
    }

    public function test_frozen_clock_advance(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 10:00:00'));
        $this->assertSame('10:00', $clock->now()->format('H:i'));

        $clock->advance(3600); // +1 hour
        $this->assertSame('11:00', $clock->now()->format('H:i'));
    }

    public function test_get_due_tasks(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 02:00:00'));
        $schedule = new Schedule($clock, $this->lockDir);

        $schedule->call(fn() => null, 'task1')->dailyAt('02:00');
        $schedule->call(fn() => null, 'task2')->dailyAt('03:00');

        $due = $schedule->getDueTasks();
        $this->assertCount(1, $due);
        $this->assertSame('task1', $due[0]->getDescription());
    }

    public function test_cron_expression_parsing(): void
    {
        // Every hour at minute 30
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 10:30:00'));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'cron-test')->cron('30 * * * *');

        $schedule->tick();
        $this->assertTrue($executed);
    }

    public function test_weekly_task(): void
    {
        // Sunday = day 0, midnight
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-14 00:00:00')); // Sunday
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'weekly')->weekly();

        $schedule->tick();
        $this->assertTrue($executed);
    }

    public function test_system_clock_returns_current_time(): void
    {
        $clock = new SystemClock();
        $before = time();
        $now = $clock->now()->getTimestamp();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }

    public function test_timezone_aware_scheduling(): void
    {
        // UTC 20:30 = IST 02:00 (UTC+5:30)
        $clock = new FrozenClock(new DateTimeImmutable('2024-01-15 20:30:00', new \DateTimeZone('UTC')));
        $schedule = new Schedule($clock, $this->lockDir);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        }, 'ist-task')->dailyAt('02:00')->timezone('Asia/Kolkata');

        $schedule->tick();
        $this->assertTrue($executed);
    }
}

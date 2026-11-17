<?php

declare(strict_types=1);

namespace Nex\Tests\Concurrency;

use Nex\Concurrency\Scheduler;
use Nex\Concurrency\TimeoutException;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
    }

    public function testSingleRun(): void
    {
        $result = $this->scheduler->run(fn() => 'computed_value');
        $this->assertSame('computed_value', $result);
    }

    public function testCooperativeTaskInterleavingWithAll(): void
    {
        $trace = [];

        $task1 = function () use (&$trace) {
            $trace[] = 't1_step1';
            Scheduler::suspend();
            $trace[] = 't1_step2';
            return 'res1';
        };

        $task2 = function () use (&$trace) {
            $trace[] = 't2_step1';
            Scheduler::suspend();
            $trace[] = 't2_step2';
            return 'res2';
        };

        $results = $this->scheduler->all([
            'a' => $task1,
            'b' => $task2,
        ]);

        $this->assertSame(['a' => 'res1', 'b' => 'res2'], $results);
        // Assert that both tasks interleaved their execution steps cooperatively
        $this->assertSame(['t1_step1', 't2_step1', 't1_step2', 't2_step2'], $trace);
    }

    public function testRaceReturnsFirstCompleted(): void
    {
        $fast = function () {
            return 'fast_winner';
        };

        $slow = function () {
            Scheduler::suspend();
            Scheduler::suspend();
            return 'slow_loser';
        };

        $winner = $this->scheduler->race([$fast, $slow]);
        $this->assertSame('fast_winner', $winner);
    }

    public function testTimeoutExceededThrowsException(): void
    {
        $this->expectException(TimeoutException::class);

        $this->scheduler->withTimeout(0.01, function () {
            usleep(20000); // 20ms > 10ms
            return 'delayed';
        });
    }

    public function testTimeoutWithinLimitSucceeds(): void
    {
        $result = $this->scheduler->withTimeout(0.5, function () {
            return 'fast';
        });

        $this->assertSame('fast', $result);
    }
}

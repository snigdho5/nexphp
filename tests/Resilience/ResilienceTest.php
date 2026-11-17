<?php

declare(strict_types=1);

namespace Nex\Tests\Resilience;

use Nex\Resilience\CircuitBreaker;
use Nex\Resilience\CircuitBreakerOpenException;
use Nex\Resilience\CircuitState;
use Nex\Resilience\Retry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResilienceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Circuit Breaker Tests
    // -------------------------------------------------------------------------

    public function test_circuit_breaker_starts_closed(): void
    {
        $breaker = new CircuitBreaker('payment-gateway', failureThreshold: 3);
        $this->assertSame(CircuitState::CLOSED, $breaker->getState());

        $result = $breaker->execute(fn() => 'success');
        $this->assertSame('success', $result);
    }

    public function test_circuit_breaker_trips_to_open_after_failures(): void
    {
        $breaker = new CircuitBreaker('payment-gateway', failureThreshold: 2, resetTimeoutSeconds: 5);

        // Failure 1
        try {
            $breaker->execute(function () { throw new RuntimeException('fail 1'); });
        } catch (RuntimeException) {}
        $this->assertSame(CircuitState::CLOSED, $breaker->getState());

        // Failure 2 -> Trips to OPEN
        try {
            $breaker->execute(function () { throw new RuntimeException('fail 2'); });
        } catch (RuntimeException) {}
        $this->assertSame(CircuitState::OPEN, $breaker->getState());

        // Call while OPEN -> fails fast without calling closure
        $called = false;
        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->execute(function () use (&$called) {
            $called = true;
            return 'should not run';
        });

        $this->assertFalse($called);
    }

    public function test_circuit_breaker_half_open_recovery(): void
    {
        // 0 second reset timeout so it immediately transitions to half-open
        $breaker = new CircuitBreaker('payment-gateway', failureThreshold: 1, resetTimeoutSeconds: 0, successThreshold: 1);

        try {
            $breaker->execute(fn() => throw new RuntimeException('error'));
        } catch (RuntimeException) {}

        // Next call should be HALF_OPEN and succeed -> returns to CLOSED
        $res = $breaker->execute(fn() => 'recovered');
        $this->assertSame('recovered', $res);
        $this->assertSame(CircuitState::CLOSED, $breaker->getState());
    }

    // -------------------------------------------------------------------------
    // Retry Tests
    // -------------------------------------------------------------------------

    public function test_retry_succeeds_first_attempt(): void
    {
        $attempts = 0;
        $result = Retry::run(function () use (&$attempts) {
            $attempts++;
            return 'done';
        }, maxAttempts: 3);

        $this->assertSame('done', $result);
        $this->assertSame(1, $attempts);
    }

    public function test_retry_succeeds_after_failures(): void
    {
        $attempts = 0;
        $result = Retry::run(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException("transient error {$attempts}");
            }
            return 'eventual success';
        }, maxAttempts: 3, initialDelayMs: 1);

        $this->assertSame('eventual success', $result);
        $this->assertSame(3, $attempts);
    }

    public function test_retry_rethrows_exception_when_max_attempts_exceeded(): void
    {
        $attempts = 0;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persistent failure');

        Retry::run(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('persistent failure');
        }, maxAttempts: 3, initialDelayMs: 1);

        $this->assertSame(3, $attempts);
    }

    public function test_retry_respects_when_predicate(): void
    {
        $attempts = 0;
        $this->expectException(RuntimeException::class);

        // Only retry if message contains 'retryable'
        Retry::run(
            action: function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('fatal non-retryable error');
            },
            maxAttempts: 3,
            initialDelayMs: 1,
            when: fn (\Throwable $e) => str_contains($e->getMessage(), 'retryable'),
        );

        // Should not have retried
        $this->assertSame(1, $attempts);
    }
}

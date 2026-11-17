<?php

declare(strict_types=1);

namespace Nex\Resilience;

use Throwable;

enum CircuitState: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half_open';
}

/**
 * Circuit Breaker pattern for protecting against cascading failures of external dependencies.
 *
 * States:
 * - CLOSED: Calls pass through. Failures are counted.
 * - OPEN: Calls fail immediately with CircuitBreakerOpenException without hitting external service.
 * - HALF_OPEN: Probe calls are allowed through after $resetTimeout to test if the service has recovered.
 */
final class CircuitBreaker
{
    private CircuitState $state = CircuitState::CLOSED;
    private int $failureCount = 0;
    private int $successCount = 0;
    private ?int $trippedAt = null;

    /**
     * @param string $service Service identifier
     * @param int $failureThreshold Number of consecutive failures to trip the circuit
     * @param int $resetTimeoutSeconds Seconds to wait before transitioning from OPEN to HALF_OPEN
     * @param int $successThreshold Number of successes in HALF_OPEN to return to CLOSED
     */
    public function __construct(
        private readonly string $service,
        private readonly int $failureThreshold = 5,
        private readonly int $resetTimeoutSeconds = 30,
        private readonly int $successThreshold = 2,
    ) {}

    /**
     * Execute an action protected by the circuit breaker.
     *
     * @template T
     * @param callable():T $action
     * @return T
     * @throws CircuitBreakerOpenException|Throwable
     */
    public function execute(callable $action): mixed
    {
        $this->evaluateState();

        if ($this->state === CircuitState::OPEN) {
            $retryAfter = max(1, ($this->trippedAt + $this->resetTimeoutSeconds) - time());
            throw new CircuitBreakerOpenException($this->service, $retryAfter);
        }

        try {
            $result = $action();
            $this->onSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    public function getState(): CircuitState
    {
        $this->evaluateState();
        return $this->state;
    }

    public function reset(): void
    {
        $this->state = CircuitState::CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->trippedAt = null;
    }

    private function evaluateState(): void
    {
        if ($this->state === CircuitState::OPEN && $this->trippedAt !== null) {
            if ((time() - $this->trippedAt) >= $this->resetTimeoutSeconds) {
                $this->state = CircuitState::HALF_OPEN;
                $this->successCount = 0;
            }
        }
    }

    private function onSuccess(): void
    {
        if ($this->state === CircuitState::HALF_OPEN) {
            $this->successCount++;
            if ($this->successCount >= $this->successThreshold) {
                $this->state = CircuitState::CLOSED;
                $this->failureCount = 0;
            }
        } else {
            $this->failureCount = 0;
        }
    }

    private function onFailure(): void
    {
        $this->failureCount++;

        if ($this->state === CircuitState::HALF_OPEN || $this->failureCount >= $this->failureThreshold) {
            $this->state = CircuitState::OPEN;
            $this->trippedAt = time();
        }
    }
}

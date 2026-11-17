<?php

declare(strict_types=1);

namespace Nex\Health;

/**
 * Health check orchestrator managing liveness and readiness probes.
 *
 * Safe operational rules:
 * - Does not expose internal credentials, passwords, or topology.
 * - Separates liveness (is process running?) from readiness (can process handle traffic?).
 */
final class HealthManager
{
    /**
     * @var array<string, HealthCheckInterface>
     */
    private array $checks = [];

    public function register(HealthCheckInterface $check): static
    {
        $this->checks[$check->name()] = $check;
        return $this;
    }

    /**
     * Liveness check: confirms the PHP runtime/worker is alive.
     * Fast, lightweight, has no external service dependencies.
     *
     * @return array{status: 'ok', timestamp: int, uptime: float}
     */
    public function liveness(): array
    {
        return [
            'status'    => 'ok',
            'timestamp' => time(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * Readiness check: executes all registered subsystem checks.
     * Returns 200 HTTP status if all pass, 503 if any check is critical.
     *
     * @return array{status: 'ok'|'degraded'|'unhealthy', checks: array<string, array<string, mixed>>}
     */
    public function readiness(): array
    {
        $overallStatus = 'ok';
        $results = [];

        foreach ($this->checks as $name => $check) {
            $result = $check->check();
            $results[$name] = $result->toArray();

            if ($result->status === 'critical') {
                $overallStatus = 'unhealthy';
            } elseif ($result->status === 'warning' && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        }

        return [
            'status' => $overallStatus,
            'checks' => $results,
        ];
    }

    public function isReady(): bool
    {
        $report = $this->readiness();
        return $report['status'] !== 'unhealthy';
    }
}

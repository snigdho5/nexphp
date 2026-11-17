<?php

declare(strict_types=1);

namespace Nex\WorkerHealth;

class WorkerDiagnostics
{
    private float $startTime;
    private int $requestCount = 0;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function recordRequest(): void
    {
        $this->requestCount++;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getUptime(): float
    {
        return microtime(true) - $this->startTime;
    }

    public function getCurrentMemory(): int
    {
        return memory_get_usage(true);
    }

    public function getPeakMemory(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'uptime_seconds' => round($this->getUptime(), 2),
            'requests_handled' => $this->requestCount,
            'memory_current_bytes' => $this->getCurrentMemory(),
            'memory_current_mb' => round($this->getCurrentMemory() / (1024 * 1024), 2),
            'memory_peak_bytes' => $this->getPeakMemory(),
            'memory_peak_mb' => round($this->getPeakMemory() / (1024 * 1024), 2),
        ];
    }
}

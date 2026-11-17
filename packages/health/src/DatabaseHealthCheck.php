<?php

declare(strict_types=1);

namespace Nex\Health;

use Nex\Database\Contracts\ConnectionInterface;
use Throwable;

final class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $name = 'database',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        $start = microtime(true);

        try {
            $this->connection->select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return HealthCheckResult::ok($this->name, 'Database connection healthy', [
                'latency_ms' => $latencyMs,
            ]);
        } catch (Throwable $e) {
            // Note: Never expose credentials or connection strings in error message
            return HealthCheckResult::critical($this->name, 'Database query failed');
        }
    }
}

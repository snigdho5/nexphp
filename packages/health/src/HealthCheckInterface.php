<?php

declare(strict_types=1);

namespace Nex\Health;

/**
 * Contract for application health checks (database, cache, storage, queue).
 */
interface HealthCheckInterface
{
    public function name(): string;

    public function check(): HealthCheckResult;
}

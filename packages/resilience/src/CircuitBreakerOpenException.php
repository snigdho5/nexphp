<?php

declare(strict_types=1);

namespace Nex\Resilience;

use RuntimeException;

class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(string $service, int $retryAfterSeconds)
    {
        parent::__construct("Circuit breaker for [{$service}] is OPEN. Retry after {$retryAfterSeconds} seconds.");
    }
}

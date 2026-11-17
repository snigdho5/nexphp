<?php

declare(strict_types=1);

namespace Nex\Runtime\Contracts;

class WorkerConfiguration
{
    public function __construct(
        public int $maxRequests = 10000,
        public int $maxMemoryBytes = 134217728, // 128 MB
        public int $maxAgeSeconds = 3600, // 1 hour
        public int $shutdownTimeoutSeconds = 10
    ) {
    }
}

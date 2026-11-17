<?php

declare(strict_types=1);

namespace Nex\Runtime\Contracts;

class RuntimeCapabilities implements RuntimeCapabilitiesInterface
{
    public function __construct(
        private bool $persistentWorkers = false,
        private bool $streamingResponses = true,
        private bool $webSockets = false,
        private bool $asyncIO = false,
        private bool $gracefulShutdown = true
    ) {
    }

    public function supportsPersistentWorkers(): bool
    {
        return $this->persistentWorkers;
    }

    public function supportsStreamingResponses(): bool
    {
        return $this->streamingResponses;
    }

    public function supportsWebSockets(): bool
    {
        return $this->webSockets;
    }

    public function supportsAsyncIO(): bool
    {
        return $this->asyncIO;
    }

    public function supportsGracefulShutdown(): bool
    {
        return $this->gracefulShutdown;
    }
}

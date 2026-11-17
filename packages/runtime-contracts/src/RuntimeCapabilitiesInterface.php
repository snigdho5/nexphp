<?php

declare(strict_types=1);

namespace Nex\Runtime\Contracts;

interface RuntimeCapabilitiesInterface
{
    public function supportsPersistentWorkers(): bool;

    public function supportsStreamingResponses(): bool;

    public function supportsWebSockets(): bool;

    public function supportsAsyncIO(): bool;

    public function supportsGracefulShutdown(): bool;
}

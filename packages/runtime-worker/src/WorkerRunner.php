<?php

declare(strict_types=1);

namespace Nex\Runtime\Worker;

use Closure;
use Nex\Contracts\Foundation\ApplicationInterface;
use Nex\Contracts\Http\ResponseEmitterInterface;
use Nex\Http\ResponseEmitter;
use Nex\Runtime\Contracts\RunnerInterface;
use Nex\Runtime\Contracts\WorkerConfiguration;
use Nex\Runtime\Contracts\WorkerLifecycleInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class WorkerRunner implements RunnerInterface
{
    private int $processedRequests = 0;
    private float $startedAt;
    private bool $running = true;
    private ?string $recycleReason = null;

    public function __construct(
        private WorkerConfiguration $config = new WorkerConfiguration(),
        private ?WorkerLifecycleInterface $lifecycle = null,
        private ?ResponseEmitterInterface $emitter = null,
        private ?Closure $requestProvider = null
    ) {
        $this->startedAt = microtime(true);
        $this->emitter ??= new ResponseEmitter();
    }

    public function run(ApplicationInterface $app): void
    {
        $this->lifecycle?->onWorkerStart();

        try {
            while ($this->running) {
                // Fetch next request
                $request = $this->fetchNextRequest();
                if ($request === null) {
                    break;
                }

                $this->processRequest($app, $request);

                if ($this->shouldRecycle()) {
                    break;
                }
            }
        } finally {
            $this->lifecycle?->onWorkerStop();
        }
    }

    public function processRequest(ApplicationInterface $app, ServerRequestInterface $request): ResponseInterface
    {
        $this->processedRequests++;
        $this->lifecycle?->onRequestStart();

        $response = null;

        try {
            $response = $app->handle($request);
            $this->emitter?->emit($response);
            return $response;
        } finally {
            try {
                if ($response !== null) {
                    $app->terminate($request, $response);
                }
            } finally {
                $this->lifecycle?->onRequestEnd();
            }
        }
    }

    public function shouldRecycle(): bool
    {
        if ($this->processedRequests >= $this->config->maxRequests) {
            $this->recycleReason = "Max requests limit reached ({$this->processedRequests}/{$this->config->maxRequests})";
            return true;
        }

        $memory = memory_get_usage(true);
        if ($memory >= $this->config->maxMemoryBytes) {
            $this->recycleReason = "Memory limit exceeded ({$memory}/{$this->config->maxMemoryBytes} bytes)";
            return true;
        }

        $age = microtime(true) - $this->startedAt;
        if ($age >= $this->config->maxAgeSeconds) {
            $this->recycleReason = "Worker age limit reached ({$age}/{$this->config->maxAgeSeconds}s)";
            return true;
        }

        return false;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function getProcessedRequests(): int
    {
        return $this->processedRequests;
    }

    public function getRecycleReason(): ?string
    {
        return $this->recycleReason;
    }

    private function fetchNextRequest(): ?ServerRequestInterface
    {
        if ($this->requestProvider !== null) {
            return ($this->requestProvider)();
        }

        return null;
    }
}

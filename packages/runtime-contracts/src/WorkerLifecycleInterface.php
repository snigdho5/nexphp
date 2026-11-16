<?php

declare(strict_types=1);

namespace Nex\Runtime\Contracts;

interface WorkerLifecycleInterface
{
    /**
     * Triggered when a worker process boots.
     */
    public function onWorkerStart(): void;

    /**
     * Triggered before an individual request is handled by the worker.
     */
    public function onRequestStart(): void;

    /**
     * Triggered after an individual request finishes and response is sent.
     */
    public function onRequestEnd(): void;

    /**
     * Triggered when a worker process is shutting down.
     */
    public function onWorkerStop(): void;
}

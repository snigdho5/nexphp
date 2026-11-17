<?php

declare(strict_types=1);

namespace Nex\Concurrency\Contracts;

use Throwable;

interface TaskInterface
{
    public function isComplete(): bool;

    public function isCancelled(): bool;

    public function isFailed(): bool;

    public function result(): mixed;

    public function error(): ?Throwable;

    public function cancel(): void;
}

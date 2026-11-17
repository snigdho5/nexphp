<?php

declare(strict_types=1);

namespace Nex\Concurrency;

use Fiber;
use Nex\Concurrency\Contracts\TaskInterface;
use RuntimeException;
use Throwable;

class Task implements TaskInterface
{
    private Fiber $fiber;
    private bool $completed = false;
    private bool $cancelled = false;
    private bool $failed = false;
    private mixed $result = null;
    private ?Throwable $error = null;

    public function __construct(callable $callback)
    {
        $this->fiber = new Fiber(function () use ($callback) {
            try {
                $this->result = $callback();
                $this->completed = true;
            } catch (Throwable $e) {
                $this->failed = true;
                $this->error = $e;
                throw $e;
            }
        });
    }

    public function getFiber(): Fiber
    {
        return $this->fiber;
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function result(): mixed
    {
        if ($this->failed && $this->error !== null) {
            throw $this->error;
        }

        if (!$this->completed && !$this->cancelled) {
            throw new RuntimeException('Cannot get result of incomplete task.');
        }

        return $this->result;
    }

    public function error(): ?Throwable
    {
        return $this->error;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }
}

<?php

declare(strict_types=1);

namespace Nex\Concurrency;

use RuntimeException;

class TimeoutException extends RuntimeException
{
    public function __construct(float $seconds)
    {
        parent::__construct("Operation timed out after {$seconds} seconds.");
    }
}

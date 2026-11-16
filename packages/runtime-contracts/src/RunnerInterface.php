<?php

declare(strict_types=1);

namespace Nex\Runtime\Contracts;

use Nex\Contracts\Foundation\ApplicationInterface;

interface RunnerInterface
{
    /**
     * Execute the application within the specific runtime.
     */
    public function run(ApplicationInterface $app): void;
}

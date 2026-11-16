<?php

declare(strict_types=1);

namespace Nex\Contracts\Container;

use Psr\Container\ContainerExceptionInterface as PsrContainerExceptionInterface;
use Throwable;

interface ContainerExceptionInterface extends PsrContainerExceptionInterface, Throwable
{
}

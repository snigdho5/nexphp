<?php

declare(strict_types=1);

namespace Nex\Contracts\Container;

use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;
use Throwable;

interface NotFoundExceptionInterface extends PsrNotFoundExceptionInterface, Throwable
{
}

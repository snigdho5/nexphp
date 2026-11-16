<?php

declare(strict_types=1);

namespace Nex\Container\Exceptions;

use Exception;
use Nex\Contracts\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}

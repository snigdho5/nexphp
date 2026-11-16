<?php

declare(strict_types=1);

namespace Nex\Contracts\Container;

use Exception;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
}

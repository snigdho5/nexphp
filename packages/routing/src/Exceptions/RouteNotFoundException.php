<?php

declare(strict_types=1);

namespace Nex\Routing\Exceptions;

use RuntimeException;

class RouteNotFoundException extends RuntimeException
{
    public function __construct(string $path = '', string $method = 'GET')
    {
        parent::__construct("No route found for [$method $path].", 404);
    }
}

<?php

declare(strict_types=1);

namespace Nex\Routing\Exceptions;

use RuntimeException;

class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(private array $allowedMethods, string $method = '', string $path = '')
    {
        $allowed = implode(', ', $allowedMethods);
        parent::__construct("Method [$method] not allowed for path [$path]. Allowed: $allowed.", 405);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}

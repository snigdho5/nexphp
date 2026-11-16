<?php

declare(strict_types=1);

namespace Nex\Authorization\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct(string $message = 'This action is unauthorized.')
    {
        parent::__construct($message, 403);
    }
}

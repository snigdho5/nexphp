<?php

declare(strict_types=1);

namespace Nex\Auth\Contracts;

interface AuthenticatableInterface
{
    public function getAuthIdentifier(): string|int;

    public function getAuthPassword(): string;
}

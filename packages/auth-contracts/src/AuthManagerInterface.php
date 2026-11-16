<?php

declare(strict_types=1);

namespace Nex\Auth\Contracts;

interface AuthManagerInterface
{
    public function guard(?string $name = null): GuardInterface;

    public function user(): ?AuthenticatableInterface;

    public function check(): bool;
}

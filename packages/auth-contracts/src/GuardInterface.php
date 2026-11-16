<?php

declare(strict_types=1);

namespace Nex\Auth\Contracts;

interface GuardInterface
{
    public function check(): bool;

    public function guest(): bool;

    public function user(): ?AuthenticatableInterface;

    public function id(): string|int|null;

    public function setUser(AuthenticatableInterface $user): void;
}

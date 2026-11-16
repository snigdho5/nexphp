<?php

declare(strict_types=1);

namespace Nex\Auth;

use InvalidArgumentException;
use Nex\Auth\Contracts\AuthenticatableInterface;
use Nex\Auth\Contracts\AuthManagerInterface;
use Nex\Auth\Contracts\GuardInterface;

class AuthManager implements AuthManagerInterface
{
    /**
     * @var array<string, GuardInterface>
     */
    private array $guards = [];

    private string $defaultGuard = 'token';

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            throw new InvalidArgumentException("Guard [$name] is not registered.");
        }

        return $this->guards[$name];
    }

    public function extend(string $name, GuardInterface $guard): static
    {
        $this->guards[$name] = $guard;
        return $this;
    }

    public function setDefaultGuard(string $name): static
    {
        $this->defaultGuard = $name;
        return $this;
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->guard()->user();
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function id(): string|int|null
    {
        return $this->guard()->id();
    }
}

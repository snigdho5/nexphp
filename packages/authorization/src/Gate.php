<?php

declare(strict_types=1);

namespace Nex\Authorization;

use Nex\Auth\Contracts\AuthenticatableInterface;
use Nex\Auth\Contracts\AuthManagerInterface;
use Nex\Authorization\Contracts\GateInterface;
use Nex\Authorization\Exceptions\AuthorizationException;
use Nex\Container\Container;

class Gate implements GateInterface
{
    /**
     * @var array<string, callable>
     */
    private array $abilities = [];

    /**
     * @var array<string, class-string|object>
     */
    private array $policies = [];

    private mixed $resolvedUser = null;
    private bool $hasCustomUser = false;

    public function __construct(
        private ?AuthManagerInterface $auth = null
    ) {
    }

    public function forUser(mixed $user): static
    {
        $clone = clone $this;
        $clone->resolvedUser = $user;
        $clone->hasCustomUser = true;
        return $clone;
    }

    public function define(string $ability, callable $callback): static
    {
        $this->abilities[$ability] = $callback;
        return $this;
    }

    public function policy(string $class, string|object $policy): static
    {
        $this->policies[$class] = $policy;
        return $this;
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        $user = $this->resolveUser();

        // Check if an ability callback was defined
        if (isset($this->abilities[$ability])) {
            return (bool) ($this->abilities[$ability])($user, ...$arguments);
        }

        // Check policy if first argument is an object
        if (!empty($arguments) && is_object($arguments[0])) {
            $class = get_class($arguments[0]);
            if (isset($this->policies[$class])) {
                $policy = $this->resolvePolicy($this->policies[$class]);
                if (method_exists($policy, $ability)) {
                    return (bool) $policy->$ability($user, ...$arguments);
                }
            }
        }

        // Deny by default
        return false;
    }

    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }

    public function authorize(string $ability, mixed ...$arguments): void
    {
        if ($this->denies($ability, ...$arguments)) {
            throw new AuthorizationException();
        }
    }

    private function resolveUser(): mixed
    {
        if ($this->hasCustomUser) {
            return $this->resolvedUser;
        }

        return $this->auth?->user();
    }

    private function resolvePolicy(string|object $policy): object
    {
        if (is_object($policy)) {
            return $policy;
        }

        return Container::getInstance()->make($policy);
    }
}

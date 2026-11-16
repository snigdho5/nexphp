<?php

declare(strict_types=1);

namespace Nex\Authorization\Contracts;

interface GateInterface
{
    public function define(string $ability, callable $callback): static;

    /**
     * @param class-string $class
     * @param class-string|object $policy
     */
    public function policy(string $class, string|object $policy): static;

    public function allows(string $ability, mixed ...$arguments): bool;

    public function denies(string $ability, mixed ...$arguments): bool;

    public function authorize(string $ability, mixed ...$arguments): void;
}

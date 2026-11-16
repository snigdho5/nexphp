<?php

declare(strict_types=1);

namespace Nex\Auth\Contracts;

interface UserProviderInterface
{
    public function retrieveById(string|int $id): ?AuthenticatableInterface;

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
}

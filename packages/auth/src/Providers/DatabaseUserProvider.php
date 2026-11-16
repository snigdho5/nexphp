<?php

declare(strict_types=1);

namespace Nex\Auth\Providers;

use Nex\Auth\Contracts\AuthenticatableInterface;
use Nex\Auth\Contracts\UserProviderInterface;
use Nex\Auth\GenericUser;
use Nex\Auth\Password;
use Nex\Database\Contracts\ConnectionInterface;

class DatabaseUserProvider implements UserProviderInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'users'
    ) {
    }

    public function retrieveById(string|int $id): ?AuthenticatableInterface
    {
        $user = $this->connection->table($this->table)->where('id', $id)->first();
        return $user ? new GenericUser($user) : null;
    }

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        $query = $this->connection->table($this->table);

        foreach ($credentials as $key => $value) {
            if ($key === 'password' || $key === 'password_hash') {
                continue;
            }
            $query->where($key, $value);
        }

        $user = $query->first();
        return $user ? new GenericUser($user) : null;
    }

    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        if (!is_string($plain)) {
            return false;
        }

        return Password::verify($plain, $user->getAuthPassword());
    }
}

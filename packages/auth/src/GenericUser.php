<?php

declare(strict_types=1);

namespace Nex\Auth;

use JsonSerializable;
use Nex\Auth\Contracts\AuthenticatableInterface;

class GenericUser implements AuthenticatableInterface, JsonSerializable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private array $attributes)
    {
    }

    public function getAuthIdentifier(): string|int
    {
        return $this->attributes['id'] ?? 0;
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? $this->attributes['password_hash'] ?? '');
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->attributes;
        unset($data['password'], $data['password_hash']);
        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

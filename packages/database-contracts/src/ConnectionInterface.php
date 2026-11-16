<?php

declare(strict_types=1);

namespace Nex\Database\Contracts;

use PDO;

interface ConnectionInterface
{
    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): bool;

    public function lastInsertId(?string $name = null): string|false;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    public function driverName(): string;

    public function table(string $table): QueryBuilderInterface;

    public function getPdo(): PDO;
}

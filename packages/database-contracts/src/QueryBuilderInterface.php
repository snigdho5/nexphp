<?php

declare(strict_types=1);

namespace Nex\Database\Contracts;

interface QueryBuilderInterface
{
    /**
     * @param list<string>|string $columns
     */
    public function select(array|string $columns = ['*']): static;

    public function from(string $table): static;

    public function where(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): static;

    public function whereNull(string $column): static;

    public function whereNotNull(string $column): static;

    public function orderBy(string $column, string $direction = 'asc'): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array;

    public function count(): int;

    public function exists(): bool;

    /**
     * @param array<string, mixed> $values
     */
    public function insert(array $values): bool;

    /**
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values): string|int|false;

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int;

    public function delete(): int;

    public function toSql(): string;

    /**
     * @return list<mixed>
     */
    public function getBindings(): array;
}

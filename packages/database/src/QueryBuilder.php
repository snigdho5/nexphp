<?php

declare(strict_types=1);

namespace Nex\Database;

use Nex\Database\Compilers\SqlCompiler;
use Nex\Database\Contracts\ConnectionInterface;
use Nex\Database\Contracts\QueryBuilderInterface;

class QueryBuilder implements QueryBuilderInterface
{
    private string $table = '';

    /**
     * @var list<string>
     */
    private array $columns = ['*'];

    /**
     * @var list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}>
     */
    private array $wheres = [];

    /**
     * @var list<array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limit = null;
    private ?int $offset = null;

    /**
     * @var list<mixed>
     */
    private array $bindings = [];

    public function __construct(
        private ConnectionInterface $connection,
        private ?SqlCompiler $compiler = null
    ) {
        $this->compiler ??= new SqlCompiler($connection->driverName());
    }

    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select(array|string $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? array_values($columns) : [$columns];
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => (string) $operator,
            'value' => $value,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
        ];

        foreach ($values as $val) {
            $this->bindings[] = $val;
        }

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
        ];

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
        ];

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    public function first(): ?array
    {
        $results = (clone $this)->limit(1)->get();
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) as aggregate'];
        $result = $clone->first();

        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    public function insert(array $values): bool
    {
        $sql = $this->compiler->compileInsert($this->table, $values);
        return $this->connection->insert($sql, array_values($values));
    }

    public function insertGetId(array $values): string|int|false
    {
        $this->insert($values);
        $id = $this->connection->lastInsertId();

        if ($id !== false && is_numeric($id)) {
            return (int) $id;
        }

        return $id;
    }

    public function update(array $values): int
    {
        $sql = $this->compiler->compileUpdate($this->table, $values, $this->wheres);
        $allBindings = array_merge(array_values($values), $this->bindings);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($allBindings);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = $this->compiler->compileDelete($this->table, $this->wheres);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    public function toSql(): string
    {
        return $this->compiler->compileSelect(
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->limit,
            $this->offset
        );
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}

<?php

declare(strict_types=1);

namespace Nex\Database\Compilers;

use InvalidArgumentException;

class SqlCompiler
{
    protected const array ALLOWED_OPERATORS = [
        '=', '!=', '<>', '<', '<=', '>', '>=',
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE',
        'REGEXP', 'NOT REGEXP', 'RLIKE',
        '&', '|', '^', '<<', '>>',
    ];

    public function __construct(protected string $driver = 'sqlite')
    {
    }

    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        if (str_contains($value, ' as ')) {
            [$target, $alias] = explode(' as ', $value, 2);
            return $this->wrap(trim($target)) . ' AS ' . $this->wrap(trim($alias));
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\((.*?)\)$/i', $value, $matches)) {
            $func = strtoupper($matches[1]);
            $arg = $matches[2] === '*' ? '*' : $this->wrap(trim($matches[2]));
            return "$func($arg)";
        }

        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map([$this, 'wrap'], $parts));
        }

        // Validate identifier against injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("Invalid database identifier: [$value]");
        }

        return match ($this->driver) {
            'mysql' => "`$value`",
            default => "\"$value\"",
        };
    }

    /**
     * @param list<string> $columns
     * @param list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}> $wheres
     * @param list<array{column: string, direction: string}> $orders
     */
    public function compileSelect(
        string $table,
        array $columns,
        array $wheres,
        array $orders,
        ?int $limit,
        ?int $offset
    ): string {
        $cols = empty($columns) ? '*' : implode(', ', array_map([$this, 'wrap'], $columns));
        $sql = "SELECT $cols FROM " . $this->wrap($table);

        if (!empty($wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres($wheres);
        }

        if (!empty($orders)) {
            $orderParts = [];
            foreach ($orders as $order) {
                $orderParts[] = $this->wrap($order['column']) . ' ' . strtoupper($order['direction']);
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        if ($offset !== null) {
            $sql .= " OFFSET $offset";
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function compileInsert(string $table, array $values): string
    {
        $columns = array_keys($values);
        $wrappedCols = implode(', ', array_map([$this, 'wrap'], $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return "INSERT INTO " . $this->wrap($table) . " ($wrappedCols) VALUES ($placeholders)";
    }

    /**
     * @param array<string, mixed> $values
     * @param list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}> $wheres
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $assignments = [];
        foreach (array_keys($values) as $col) {
            $assignments[] = $this->wrap($col) . ' = ?';
        }

        $sql = "UPDATE " . $this->wrap($table) . " SET " . implode(', ', $assignments);

        if (!empty($wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * @param list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}> $wheres
     */
    public function compileDelete(string $table, array $wheres): string
    {
        $sql = "DELETE FROM " . $this->wrap($table);

        if (!empty($wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * @param list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}> $wheres
     */
    private function compileWheres(array $wheres): string
    {
        $clauses = [];

        foreach ($wheres as $where) {
            $col = $this->wrap($where['column']);

            $clause = match ($where['type']) {
                'basic' => $this->compileBasicWhere($col, (string) $where['operator']),
                'null' => "$col IS NULL",
                'not_null' => "$col IS NOT NULL",
                'in' => "$col IN (" . implode(', ', array_fill(0, count($where['values'] ?? []), '?')) . ")",
                default => throw new InvalidArgumentException("Unknown where clause type: {$where['type']}"),
            };

            $clauses[] = $clause;
        }

        return implode(' AND ', $clauses);
    }

    private function compileBasicWhere(string $col, string $operator): string
    {
        $normalized = strtoupper(trim($operator));
        if (!in_array($normalized, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Illegal SQL operator: [{$operator}]");
        }

        return "$col $normalized ?";
    }
}

<?php

declare(strict_types=1);

namespace Nex\Database;

use Nex\Database\Contracts\ConnectionInterface;
use Nex\Database\Contracts\QueryBuilderInterface;
use Nex\Database\Exceptions\DatabaseException;
use PDO;
use PDOException;

class Connection implements ConnectionInterface
{
    private int $transactions = 0;

    public function __construct(
        private PDO $pdo,
        private string $driver = 'sqlite'
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function select(string $sql, array $bindings = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            /** @var list<array<string, mixed>> $result */
            $result = $stmt->fetchAll();
            return $result;
        } catch (PDOException $e) {
            throw new DatabaseException("Database query failed: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($bindings);
        } catch (PDOException $e) {
            throw new DatabaseException("Database statement failed: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->statement($sql, $bindings);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function beginTransaction(): void
    {
        $this->transactions++;

        if ($this->transactions === 1) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans_{$this->transactions}");
        }
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo->commit();
        } elseif ($this->transactions > 1) {
            $this->pdo->exec("RELEASE SAVEPOINT trans_{$this->transactions}");
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            $this->pdo->rollBack();
        } elseif ($this->transactions > 1) {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactions}");
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function inTransaction(): bool
    {
        return $this->transactions > 0;
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    public function table(string $table): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->from($table);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}

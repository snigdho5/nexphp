<?php

declare(strict_types=1);

namespace Nex\Database\Migrations\Schema;

use Closure;
use Nex\Database\Contracts\ConnectionInterface;

class Schema
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $blueprint->toSql($this->connection);
        $this->connection->statement($sql);
    }

    public function dropIfExists(string $table): void
    {
        $driver = $this->connection->driverName();
        $wrapped = $driver === 'mysql' ? "`$table`" : "\"$table\"";
        $this->connection->statement("DROP TABLE IF EXISTS $wrapped");
    }
}

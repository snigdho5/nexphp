<?php

declare(strict_types=1);

namespace Nex\Database\Contracts;

interface DatabaseManagerInterface
{
    public function connection(?string $name = null): ConnectionInterface;

    public function transaction(callable $callback, ?string $connection = null): mixed;

    public function table(string $table, ?string $connection = null): QueryBuilderInterface;
}

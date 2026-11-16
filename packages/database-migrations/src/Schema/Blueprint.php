<?php

declare(strict_types=1);

namespace Nex\Database\Migrations\Schema;

use Nex\Database\Contracts\ConnectionInterface;

class Blueprint
{
    /**
     * @var list<ColumnDefinition>
     */
    private array $columns = [];

    public function __construct(public string $table)
    {
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'id');
        $col->primary = true;
        $col->autoIncrement = true;
        $this->columns[] = $col;
        return $col;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'string', $length);
        $this->columns[] = $col;
        return $col;
    }

    public function integer(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'integer');
        $this->columns[] = $col;
        return $col;
    }

    public function boolean(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'boolean');
        $this->columns[] = $col;
        return $col;
    }

    public function text(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, 'text');
        $this->columns[] = $col;
        return $col;
    }

    public function timestamps(): void
    {
        $this->string('created_at')->nullable();
        $this->string('updated_at')->nullable();
    }

    public function toSql(ConnectionInterface $connection): string
    {
        $driver = $connection->driverName();
        $definitions = [];

        foreach ($this->columns as $col) {
            $definitions[] = $this->compileColumn($col, $driver);
        }

        $wrappedTable = $driver === 'mysql' ? "`{$this->table}`" : "\"{$this->table}\"";
        return "CREATE TABLE IF NOT EXISTS $wrappedTable (" . implode(', ', $definitions) . ")";
    }

    private function compileColumn(ColumnDefinition $col, string $driver): string
    {
        $name = $driver === 'mysql' ? "`{$col->name}`" : "\"{$col->name}\"";

        if ($col->type === 'id') {
            return match ($driver) {
                'sqlite' => "$name INTEGER PRIMARY KEY AUTOINCREMENT",
                'pgsql' => "$name SERIAL PRIMARY KEY",
                default => "$name INT AUTO_INCREMENT PRIMARY KEY",
            };
        }

        $type = match ($col->type) {
            'string' => match ($driver) {
                'sqlite' => 'TEXT',
                default => "VARCHAR({$col->length})",
            },
            'integer' => match ($driver) {
                'sqlite' => 'INTEGER',
                default => 'INT',
            },
            'boolean' => match ($driver) {
                'sqlite' => 'INTEGER',
                'pgsql' => 'BOOLEAN',
                default => 'TINYINT(1)',
            },
            'text' => 'TEXT',
            default => 'TEXT',
        };

        $sql = "$name $type";

        if (!$col->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($col->unique) {
            $sql .= ' UNIQUE';
        }

        if ($col->hasDefault) {
            $default = is_string($col->default) ? "'{$col->default}'" : ($col->default === null ? 'NULL' : (string) $col->default);
            $sql .= " DEFAULT $default";
        }

        return $sql;
    }
}

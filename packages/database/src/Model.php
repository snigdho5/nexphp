<?php

declare(strict_types=1);

namespace Nex\Database;

use JsonSerializable;
use Nex\Container\Container;
use Nex\Database\Contracts\DatabaseManagerInterface;

abstract class Model implements JsonSerializable
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected array $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    protected bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->exists = $exists;
        if ($exists) {
            $this->attributes = $attributes;
        } else {
            $this->fill($attributes);
        }
    }

    public static function getTable(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        $parts = explode('\\', static::class);
        $name = strtolower(end($parts));
        return $name . 's';
    }

    public static function query(): QueryBuilder
    {
        /** @var DatabaseManagerInterface $db */
        $db = Container::getInstance()->make(DatabaseManagerInterface::class);
        $qb = $db->table(static::getTable());

        return $qb;
    }

    public static function find(int|string $id): ?static
    {
        $record = static::query()->where(static::$primaryKey, $id)->first();

        if ($record === null) {
            return null;
        }

        return new static($record, exists: true);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        $qb = static::query();
        return func_num_args() === 2 ? $qb->where($column, $operator) : $qb->where($column, $operator, $value);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $instance = new static($attributes);
        $instance->save();

        return $instance;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (empty($this->fillable) || in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function save(): bool
    {
        $pk = static::$primaryKey;

        if ($this->exists) {
            $updateData = $this->attributes;
            unset($updateData[$pk]);

            $affected = static::query()->where($pk, $this->attributes[$pk])->update($updateData);
            return $affected > 0;
        }

        $id = static::query()->insertGetId($this->attributes);
        if ($id !== false) {
            $this->attributes[$pk] = $id;
            $this->exists = true;
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        $pk = static::$primaryKey;

        if (!$this->exists || !isset($this->attributes[$pk])) {
            return false;
        }

        $affected = static::query()->where($pk, $this->attributes[$pk])->delete();
        $this->exists = false;

        return $affected > 0;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

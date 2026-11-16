<?php

declare(strict_types=1);

namespace Nex\Database\Migrations\Schema;

class ColumnDefinition
{
    public bool $nullable = false;
    public bool $unique = false;
    public mixed $default = null;
    public bool $hasDefault = false;
    public bool $autoIncrement = false;
    public bool $primary = false;

    public function __construct(
        public string $name,
        public string $type,
        public int $length = 255
    ) {
    }

    public function nullable(): static
    {
        $this->nullable = true;
        return $this;
    }

    public function unique(): static
    {
        $this->unique = true;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function primary(): static
    {
        $this->primary = true;
        return $this;
    }
}

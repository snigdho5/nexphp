<?php

declare(strict_types=1);

namespace Nex\Config;

use Nex\Contracts\Config\RepositoryInterface;
use RuntimeException;

class Repository implements RepositoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $items = [];

    private bool $frozen = false;

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->frozen) {
            throw new RuntimeException("Cannot modify frozen configuration for key [$key].");
        }

        $keys = explode('.', $key);
        $array = &$this->items;

        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }

        $array[array_shift($keys)] = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Load an array of configuration files into the repository.
     *
     * @param string $path Path to directory containing PHP config files
     */
    public function loadFromDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                $this->set($key, $data);
            }
        }
    }
}

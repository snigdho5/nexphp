<?php

declare(strict_types=1);

namespace Nex\Contracts\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a transient binding with the container.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): void;

    /**
     * Register a shared (singleton) binding with the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void;

    /**
     * Register a request-scoped binding with the container.
     */
    public function scoped(string $abstract, Closure|string|null $concrete = null): void;

    /**
     * Register an existing instance as a shared binding.
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve the given type from the container with optional parameters.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Clear all resolved request-scoped instances.
     * Vital for persistent worker runtimes to prevent state leaks.
     */
    public function forgetScoped(): void;

    /**
     * Call the given Closure / method and inject its dependencies.
     *
     * @param callable|array{0: object|string, 1: string} $callable
     * @param array<string, mixed> $parameters
     */
    public function call(callable|array $callable, array $parameters = []): mixed;
}

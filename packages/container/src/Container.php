<?php

declare(strict_types=1);

namespace Nex\Container;

use Closure;
use Nex\Container\Exceptions\NotFoundException;
use Nex\Contracts\Container\BindingResolutionException;
use Nex\Contracts\Container\CircularDependencyException;
use Nex\Contracts\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use TypeError;

class Container implements ContainerInterface
{
    private static ?ContainerInterface $instance = null;

    /**
     * @var array<string, array{concrete: Closure|string, shared: bool, scoped: bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, object>
     */
    private array $scopedInstances = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Current stack of resolving types to detect circular dependencies.
     *
     * @var list<string>
     */
    private array $buildStack = [];

    public static function getInstance(): ContainerInterface
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(?ContainerInterface $container): ?ContainerInterface
    {
        return self::$instance = $container;
    }

    public function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->registerBinding($abstract, $concrete ?? $abstract, shared: false, scoped: false);
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->registerBinding($abstract, $concrete ?? $abstract, shared: true, scoped: false);
    }

    public function scoped(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->registerBinding($abstract, $concrete ?? $abstract, shared: false, scoped: true);
    }

    public function instance(string $abstract, object $instance): void
    {
        $abstract = $this->getAlias($abstract);
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function has(string $id): bool
    {
        $id = $this->getAlias($id);

        return isset($this->instances[$id])
            || isset($this->scopedInstances[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    public function get(string $id): mixed
    {
        $id = $this->getAlias($id);

        try {
            return $this->make($id);
        } catch (BindingResolutionException $e) {
            if (!$this->has($id)) {
                throw new NotFoundException("Entry or class [$id] not found in container.", previous: $e);
            }
            throw $e;
        }
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        // Return shared singleton instance if exists
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Return request-scoped instance if exists
        if (isset($this->scopedInstances[$abstract])) {
            return $this->scopedInstances[$abstract];
        }

        // Check for circular dependency
        if (in_array($abstract, $this->buildStack, true)) {
            $cycle = implode(' -> ', [...$this->buildStack, $abstract]);
            throw new CircularDependencyException(
                "Circular dependency detected while resolving [$abstract]: $cycle"
            );
        }

        $this->buildStack[] = $abstract;

        try {
            $object = $this->build($abstract, $parameters);

            if (isset($this->bindings[$abstract])) {
                if ($this->bindings[$abstract]['shared']) {
                    $this->instances[$abstract] = $object;
                } elseif ($this->bindings[$abstract]['scoped']) {
                    $this->scopedInstances[$abstract] = $object;
                }
            }

            return $object;
        } finally {
            array_pop($this->buildStack);
        }
    }

    public function forgetScoped(): void
    {
        $this->scopedInstances = [];
    }

    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            [$target, $method] = $callable;
            if (is_string($target)) {
                $target = $this->make($target);
            }
            $reflector = new ReflectionMethod($target, $method);
            $dependencies = $this->resolveMethodParameters($reflector, $parameters);

            return $reflector->invokeArgs($target, $dependencies);
        }

        if ($callable instanceof Closure || is_string($callable)) {
            $reflector = new ReflectionFunction($callable);
            $dependencies = $this->resolveMethodParameters($reflector, $parameters);

            return $reflector->invokeArgs($dependencies);
        }

        throw new BindingResolutionException('Given callable is not a valid callable target.');
    }

    private function registerBinding(string $abstract, Closure|string $concrete, bool $shared, bool $scoped): void
    {
        $abstract = $this->getAlias($abstract);
        unset($this->instances[$abstract], $this->scopedInstances[$abstract]);

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
            'scoped' => $scoped,
        ];
    }

    private function build(string $abstract, array $parameters = []): mixed
    {
        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        // If concrete is a Closure, invoke it with container and parameters
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        if (!is_string($concrete)) {
            throw new BindingResolutionException("Cannot resolve target for [$abstract].");
        }

        // If concrete is another abstract binding, recursively resolve
        if ($concrete !== $abstract && isset($this->bindings[$concrete])) {
            return $this->make($concrete, $parameters);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [$concrete] does not exist.", previous: $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveMethodParameters($constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * @param ReflectionMethod|ReflectionFunction $reflector
     * @param array<string, mixed> $parameters
     * @return list<mixed>
     */
    private function resolveMethodParameters(ReflectionMethod|ReflectionFunction $reflector, array $parameters): array
    {
        $dependencies = [];

        foreach ($reflector->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Override by type name
                if (array_key_exists($typeName, $parameters)) {
                    $dependencies[] = $parameters[$typeName];
                    continue;
                }

                // If provided by parameter name and matches type
                if (array_key_exists($name, $parameters) && ($parameters[$name] instanceof $typeName)) {
                    $dependencies[] = $parameters[$name];
                    continue;
                }

                // Support auto-resolving FormRequest subclasses
                if (class_exists($typeName) && is_subclass_of($typeName, \Nex\Validation\FormRequest::class)) {
                    $serverRequest = $parameters[\Psr\Http\Message\ServerRequestInterface::class]
                        ?? $parameters['request']
                        ?? $this->make(\Psr\Http\Message\ServerRequestInterface::class);
                    $dependencies[] = new $typeName($serverRequest);
                    continue;
                }

                try {
                    $dependencies[] = $this->make($typeName);
                    continue;
                } catch (BindingResolutionException $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    if ($parameter->allowsNull()) {
                        $dependencies[] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            // Override by name for primitives / untyped
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            $declaringClass = $reflector instanceof ReflectionMethod ? $reflector->getDeclaringClass()->getName() . '::' : '';
            $methodName = $reflector->getName();
            throw new BindingResolutionException(
                "Cannot resolve un-typed parameter [\${$name}] in [{$declaringClass}{$methodName}()]. Provide a default value or pass it explicitly."
            );
        }

        return $dependencies;
    }

    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }
}

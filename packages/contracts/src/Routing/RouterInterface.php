<?php

declare(strict_types=1);

namespace Nex\Contracts\Routing;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function get(string $path, mixed $handler): RouteInterface;

    public function post(string $path, mixed $handler): RouteInterface;

    public function put(string $path, mixed $handler): RouteInterface;

    public function delete(string $path, mixed $handler): RouteInterface;

    public function patch(string $path, mixed $handler): RouteInterface;

    public function options(string $path, mixed $handler): RouteInterface;

    public function any(string $path, mixed $handler): RouteInterface;

    /**
     * @param list<string> $methods
     */
    public function match(array $methods, string $path, mixed $handler): RouteInterface;

    /**
     * @param array{prefix?: string, middleware?: list<string|object>|string|object} $attributes
     */
    public function group(array $attributes, Closure $routes): void;

    /**
     * Dispatch the incoming HTTP request and return a response.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface;

    /**
     * @return list<RouteInterface>
     */
    public function getRoutes(): array;
}

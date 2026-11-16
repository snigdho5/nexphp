<?php

declare(strict_types=1);

namespace Nex\Contracts\Routing;

interface RouteInterface
{
    /**
     * @return list<string>
     */
    public function getMethods(): array;

    public function getPath(): string;

    public function getHandler(): mixed;

    public function getName(): ?string;

    public function name(string $name): static;

    /**
     * @param list<class-string|object>|class-string|object $middleware
     */
    public function middleware(array|string|object $middleware): static;

    /**
     * @return list<class-string|object>
     */
    public function getMiddleware(): array;

    public function where(string $param, string $pattern): static;

    /**
     * @return array<string, string>
     */
    public function getConstraints(): array;
}

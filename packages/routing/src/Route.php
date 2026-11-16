<?php

declare(strict_types=1);

namespace Nex\Routing;

use Nex\Contracts\Routing\RouteInterface;

class Route implements RouteInterface
{
    /**
     * @var list<string>
     */
    private array $methods;

    private string $path;
    private mixed $handler;
    private ?string $name = null;

    /**
     * @var list<class-string|object>
     */
    private array $middleware = [];

    /**
     * @var array<string, string>
     */
    private array $constraints = [];

    private ?string $compiledRegex = null;

    /**
     * @var list<string>
     */
    private array $parameterNames = [];

    /**
     * @param list<string>|string $methods
     */
    public function __construct(array|string $methods, string $path, mixed $handler)
    {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->path = '/' . trim($path, '/');
        $this->handler = $handler;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function middleware(array|string|object $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $m) {
            $this->middleware[] = $m;
        }

        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function where(string $param, string $pattern): static
    {
        $this->constraints[$param] = $pattern;
        $this->compiledRegex = null; // Invalidate compiled cache
        return $this;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Test if this route matches the given HTTP method and path.
     *
     * @return array<string, string>|null Returns array of extracted parameters or null on mismatch
     */
    public function match(string $method, string $path): ?array
    {
        if (!in_array(strtoupper($method), $this->methods, true)) {
            return null;
        }

        return $this->matchPath($path);
    }

    /**
     * Test only if the path matches this route (useful for 405 Method Not Allowed checks).
     *
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        $path = '/' . trim($path, '/');

        if ($this->compiledRegex === null) {
            $this->compile();
        }

        if (!preg_match($this->compiledRegex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($this->parameterNames as $name) {
            if (isset($matches[$name])) {
                $params[$name] = $matches[$name];
            }
        }

        return $params;
    }

    private function compile(): void
    {
        $this->parameterNames = [];

        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', function ($matches) {
            $paramName = $matches[1];
            $this->parameterNames[] = $paramName;
            $constraint = $this->constraints[$paramName] ?? '[^/]+';

            return '(?P<' . $paramName . '>' . $constraint . ')';
        }, $this->path);

        $this->compiledRegex = '#^' . $pattern . '$#u';
    }
}

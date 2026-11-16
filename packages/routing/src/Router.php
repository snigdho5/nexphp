<?php

declare(strict_types=1);

namespace Nex\Routing;

use Closure;
use Nex\Container\Container;
use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Routing\RouteInterface;
use Nex\Contracts\Routing\RouterInterface;
use Nex\Http\Response;
use Nex\Http\Responses\EmptyResponse;
use Nex\Http\Responses\JsonResponse;
use Nex\Routing\Attributes\Middleware as MiddlewareAttribute;
use Nex\Routing\Attributes\Route as RouteAttribute;
use Nex\Routing\Exceptions\MethodNotAllowedException;
use Nex\Routing\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Stringable;

class Router implements RouterInterface
{
    /**
     * @var list<Route>
     */
    private array $routes = [];

    /**
     * Group attributes stack.
     *
     * @var list<array{prefix?: string, middleware?: list<class-string|object>}>
     */
    private array $groupStack = [];

    private ContainerInterface $container;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? Container::getInstance();
    }

    public function get(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute(['GET', 'HEAD'], $path, $handler);
    }

    public function post(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function patch(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function options(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    public function any(string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler);
    }

    public function match(array $methods, string $path, mixed $handler): RouteInterface
    {
        return $this->addRoute($methods, $path, $handler);
    }

    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = $attributes;

        $routes($this);

        array_pop($this->groupStack);
    }

    /**
     * Automatically discover and register routes from a controller using PHP 8.3+ attributes.
     *
     * @param class-string $controllerClass
     */
    public function registerController(string $controllerClass): void
    {
        $reflection = new ReflectionClass($controllerClass);

        $basePath = '';
        $classMiddlewares = [];

        // Check class-level Route attribute for route prefix
        foreach ($reflection->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF) as $attr) {
            /** @var RouteAttribute $inst */
            $inst = $attr->newInstance();
            $basePath = '/' . trim($inst->path, '/');
            if (!empty($inst->middleware)) {
                $classMiddlewares = array_merge($classMiddlewares, (array) $inst->middleware);
            }
        }

        // Check class-level Middleware attributes
        foreach ($reflection->getAttributes(MiddlewareAttribute::class) as $attr) {
            /** @var MiddlewareAttribute $inst */
            $inst = $attr->newInstance();
            $classMiddlewares = array_merge($classMiddlewares, (array) $inst->middleware);
        }

        // Check each public method
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (empty($routeAttributes)) {
                continue;
            }

            $methodMiddlewares = [];
            foreach ($method->getAttributes(MiddlewareAttribute::class) as $attr) {
                /** @var MiddlewareAttribute $inst */
                $inst = $attr->newInstance();
                $methodMiddlewares = array_merge($methodMiddlewares, (array) $inst->middleware);
            }

            foreach ($routeAttributes as $attr) {
                /** @var RouteAttribute $routeAttr */
                $routeAttr = $attr->newInstance();
                $path = $basePath . '/' . trim($routeAttr->path, '/');
                $route = $this->match($routeAttr->methods, $path, [$controllerClass, $method->getName()]);

                if ($routeAttr->name !== null) {
                    $route->name($routeAttr->name);
                }

                $combinedMiddlewares = array_merge($classMiddlewares, (array) $routeAttr->middleware, $methodMiddlewares);
                if (!empty($combinedMiddlewares)) {
                    $route->middleware($combinedMiddlewares);
                }
            }
        }
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        if ($path === '') {
            $path = '/';
        }

        $allowedMethodsForPath = [];

        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);

            if ($params !== null) {
                // Attach parameters and matched route to request attributes
                foreach ($params as $paramName => $paramValue) {
                    $request = $request->withAttribute($paramName, $paramValue);
                }
                $request = $request->withAttribute('__route__', $route);

                return $this->invokeRoute($route, $request, $params);
            }

            // Check if path matches for 405 detection
            if ($route->matchPath($path) !== null) {
                foreach ($route->getMethods() as $m) {
                    $allowedMethodsForPath[$m] = true;
                }
            }
        }

        if (!empty($allowedMethodsForPath)) {
            throw new MethodNotAllowedException(array_keys($allowedMethodsForPath), $method, $path);
        }

        throw new RouteNotFoundException($path, $method);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function addRoute(array|string $methods, string $path, mixed $handler): RouteInterface
    {
        $prefix = '';
        $groupMiddlewares = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddlewares = array_merge($groupMiddlewares, (array) $group['middleware']);
            }
        }

        $fullPath = $prefix . '/' . trim($path, '/');
        $route = new Route($methods, $fullPath, $handler);

        if (!empty($groupMiddlewares)) {
            $route->middleware($groupMiddlewares);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * @param array<string, string> $params
     */
    private function invokeRoute(Route $route, ServerRequestInterface $request, array $params): ResponseInterface
    {
        $handler = $route->getHandler();

        // Inject request and extracted parameters into call
        $callParams = array_merge($params, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        $result = $this->container->call($handler, $callParams);

        return $this->normalizeResponse($result);
    }

    private function normalizeResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_array($result) || $result instanceof \stdClass) {
            return new JsonResponse($result);
        }

        if ($result === null) {
            return new EmptyResponse(204);
        }

        if (is_string($result) || $result instanceof Stringable) {
            return new Response(status: 200, headers: ['Content-Type' => 'text/html; charset=utf-8'], body: (string) $result);
        }

        return new JsonResponse($result);
    }
}

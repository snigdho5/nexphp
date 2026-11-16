<?php

declare(strict_types=1);

namespace Nex\Foundation;

use Closure;
use Nex\Config\Repository;
use Nex\Container\Container;
use Nex\Contracts\Config\RepositoryInterface;
use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Foundation\ApplicationInterface;
use Nex\Contracts\Foundation\ServiceProviderInterface;
use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Contracts\Http\RequestHandlerInterface;
use Nex\Contracts\Routing\RouteInterface;
use Nex\Contracts\Routing\RouterInterface;
use Nex\Foundation\Providers\ConfigServiceProvider;
use Nex\Foundation\Providers\RoutingServiceProvider;
use Nex\Http\ResponseEmitter;
use Nex\Http\ServerRequestFactory;
use Nex\Middleware\ExceptionHandlingMiddleware;
use Nex\Middleware\Pipeline;
use Nex\Middleware\RequestIdMiddleware;
use Nex\Middleware\SecurityHeadersMiddleware;
use Nex\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;

class Application extends Container implements ApplicationInterface
{
    public const string VERSION = '1.0.0-dev';

    private string $basePath;
    private bool $booted = false;

    /**
     * @var list<ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * @var list<PsrMiddlewareInterface|class-string<PsrMiddlewareInterface>|callable>
     */
    private array $middlewares = [];

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '\/');

        self::setInstance($this);

        $this->instance(ApplicationInterface::class, $this);
        $this->instance(ContainerInterface::class, $this);
        $this->instance(self::class, $this);

        $this->registerCoreProviders();
        $this->registerCoreMiddlewares();
    }

    public static function create(string $basePath = ''): static
    {
        return new static($basePath);
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function registerProvider(ServiceProviderInterface $provider): static
    {
        $this->providers[] = $provider;
        $provider->register($this);

        if ($this->booted) {
            $provider->boot($this);
        }

        return $this;
    }

    public function use(PsrMiddlewareInterface|string|callable $middleware): static
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Load config files if directory exists
        $configDir = $this->configPath();
        if (is_dir($configDir)) {
            /** @var Repository $config */
            $config = $this->make(RepositoryInterface::class);
            $config->loadFromDirectory($configDir);
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }

        $this->booted = true;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->boot();

        // Bind request into scoped container
        $this->instance(ServerRequestInterface::class, $request);

        $router = $this->router();

        $terminal = new class($router) implements RequestHandlerInterface {
            public function __construct(private RouterInterface $router)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->router->dispatch($request);
            }
        };

        $pipeline = new Pipeline($this->middlewares, $terminal, $this);

        try {
            return $pipeline->handle($request);
        } finally {
            // Guarantee request-scoped cleanup to prevent leaks across requests
            $this->forgetScoped();
        }
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->forgetScoped();
    }

    /**
     * Run the application under traditional PHP-FPM / CLI web server.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $request ??= ServerRequestFactory::fromGlobals();
        $response = $this->handle($request);

        $emitter = new ResponseEmitter();
        $emitter->emit($response);

        $this->terminate($request, $response);
    }

    public function router(): RouterInterface
    {
        return $this->make(RouterInterface::class);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        /** @var RepositoryInterface $repo */
        $repo = $this->make(RepositoryInterface::class);
        return $repo->get($key, $default);
    }

    // Convenience Routing Proxies for AI Vibe Coding & Minimal DX
    public function get(string $id, mixed $handler = null): mixed
    {
        if ($handler !== null) {
            return $this->router()->get($id, $handler);
        }

        return parent::get($id);
    }

    public function post(string $path, mixed $handler): RouteInterface
    {
        return $this->router()->post($path, $handler);
    }

    public function put(string $path, mixed $handler): RouteInterface
    {
        return $this->router()->put($path, $handler);
    }

    public function delete(string $path, mixed $handler): RouteInterface
    {
        return $this->router()->delete($path, $handler);
    }

    public function patch(string $path, mixed $handler): RouteInterface
    {
        return $this->router()->patch($path, $handler);
    }

    public function any(string $path, mixed $handler): RouteInterface
    {
        return $this->router()->any($path, $handler);
    }

    public function group(array $attributes, Closure $routes): void
    {
        $this->router()->group($attributes, $routes);
    }

    public function registerController(string $controllerClass): void
    {
        $router = $this->router();
        if ($router instanceof Router) {
            $router->registerController($controllerClass);
        }
    }

    private function registerCoreProviders(): void
    {
        $this->registerProvider(new ConfigServiceProvider());
        $this->registerProvider(new RoutingServiceProvider());
    }

    private function registerCoreMiddlewares(): void
    {
        // Outermost error handler
        $this->use(new ExceptionHandlingMiddleware(debug: true));
        $this->use(new RequestIdMiddleware());
        $this->use(new SecurityHeadersMiddleware());
    }
}

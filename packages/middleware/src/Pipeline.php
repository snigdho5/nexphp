<?php

declare(strict_types=1);

namespace Nex\Middleware;

use Nex\Container\Container;
use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Contracts\Http\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use RuntimeException;

class Pipeline implements RequestHandlerInterface
{
    /**
     * @var list<PsrMiddlewareInterface|class-string<PsrMiddlewareInterface>|callable>
     */
    private array $middlewares = [];

    private ?PsrRequestHandlerInterface $fallbackHandler = null;
    private ContainerInterface $container;

    /**
     * @param list<PsrMiddlewareInterface|class-string<PsrMiddlewareInterface>|callable> $middlewares
     */
    public function __construct(
        array $middlewares = [],
        ?PsrRequestHandlerInterface $fallbackHandler = null,
        ?ContainerInterface $container = null
    ) {
        $this->middlewares = $middlewares;
        $this->fallbackHandler = $fallbackHandler;
        $this->container = $container ?? Container::getInstance();
    }

    /**
     * Add a middleware to the pipeline.
     */
    public function pipe(PsrMiddlewareInterface|string|callable $middleware): static
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatchMiddleware(0, $request);
    }

    public function dispatchMiddleware(int $index, ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$index])) {
            if ($this->fallbackHandler !== null) {
                return $this->fallbackHandler->handle($request);
            }
            throw new RuntimeException('Pipeline reached the end without a terminal response.');
        }

        $middleware = $this->resolveMiddleware($this->middlewares[$index]);

        $next = new class($this, $index + 1) implements RequestHandlerInterface {
            public function __construct(private Pipeline $pipeline, private int $nextIndex)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->pipeline->dispatchMiddleware($this->nextIndex, $request);
            }
        };

        if ($middleware instanceof PsrMiddlewareInterface) {
            return $middleware->process($request, $next);
        }

        if (is_callable($middleware)) {
            $response = $middleware($request, $next);
            if (!$response instanceof ResponseInterface) {
                throw new RuntimeException('Middleware callable must return a ResponseInterface.');
            }
            return $response;
        }

        throw new RuntimeException('Invalid middleware type in pipeline.');
    }

    private function resolveMiddleware(mixed $middleware): mixed
    {
        if (is_string($middleware)) {
            return $this->container->make($middleware);
        }

        return $middleware;
    }
}

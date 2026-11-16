<?php

declare(strict_types=1);

namespace Nex\Contracts\Foundation;

use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Http\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ApplicationInterface extends ContainerInterface, RequestHandlerInterface
{
    /**
     * Get the framework version.
     */
    public function version(): string;

    /**
     * Get the base path of the application installation.
     */
    public function basePath(string $path = ''): string;

    /**
     * Bootstrap the application.
     */
    public function boot(): void;

    /**
     * Perform any final actions after the response has been sent to the client.
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
}

<?php

declare(strict_types=1);

namespace Nex\Contracts\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;

interface MiddlewareInterface extends PsrMiddlewareInterface
{
    public function process(ServerRequestInterface $request, PsrRequestHandlerInterface $handler): ResponseInterface;
}

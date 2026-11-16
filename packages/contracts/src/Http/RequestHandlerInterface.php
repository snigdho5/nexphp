<?php

declare(strict_types=1);

namespace Nex\Contracts\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;

interface RequestHandlerInterface extends PsrRequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}

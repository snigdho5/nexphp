<?php

declare(strict_types=1);

namespace Nex\Authorization;

use Nex\Authorization\Contracts\GateInterface;
use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthorizeMiddleware implements MiddlewareInterface
{
    /**
     * @param list<mixed> $arguments
     */
    public function __construct(
        private GateInterface $gate,
        private string $ability,
        private array $arguments = []
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->gate->authorize($this->ability, ...$this->arguments);

        return $handler->handle($request);
    }
}

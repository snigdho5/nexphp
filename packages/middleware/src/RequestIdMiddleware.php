<?php

declare(strict_types=1);

namespace Nex\Middleware;

use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestIdMiddleware implements MiddlewareInterface
{
    public const string HEADER_NAME = 'X-Request-Id';
    public const string ATTRIBUTE_NAME = 'request_id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine(self::HEADER_NAME);

        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        $request = $request
            ->withAttribute(self::ATTRIBUTE_NAME, $requestId)
            ->withHeader(self::HEADER_NAME, $requestId);

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $requestId);
    }
}

<?php

declare(strict_types=1);

namespace Nex\Middleware;

use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\Responses\TextResponse;
use Nex\Routing\Exceptions\MethodNotAllowedException;
use Nex\Routing\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ExceptionHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $debug = false)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    private function handleException(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $status = 500;
        $headers = [];

        if ($e instanceof RouteNotFoundException) {
            $status = 404;
        } elseif ($e instanceof MethodNotAllowedException) {
            $status = 405;
            $headers['Allow'] = implode(', ', $e->getAllowedMethods());
        }

        $wantsJson = str_contains($request->getHeaderLine('Accept'), 'application/json')
            || str_starts_with($request->getUri()->getPath(), '/api');

        if ($wantsJson) {
            $data = [
                'error' => [
                    'code' => $status,
                    'message' => ($status === 500 && !$this->debug) ? 'Internal Server Error' : $e->getMessage(),
                ],
            ];

            if ($this->debug && $status === 500) {
                $data['error']['exception'] = get_class($e);
                $data['error']['file'] = $e->getFile();
                $data['error']['line'] = $e->getLine();
                $data['error']['trace'] = explode("\n", $e->getTraceAsString());
            }

            return new JsonResponse($data, status: $status, headers: $headers);
        }

        $message = ($status === 500 && !$this->debug) ? '500 | Internal Server Error' : "{$status} | {$e->getMessage()}";
        return new TextResponse($message, status: $status, headers: $headers);
    }
}

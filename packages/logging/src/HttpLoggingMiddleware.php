<?php

declare(strict_types=1);

namespace Nex\Logging;

use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Access logging middleware that records structured HTTP request and response metrics.
 */
final class HttpLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $requestId = $request->getHeaderLine('X-Request-Id');

        $response = $handler->handle($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $status = $response->getStatusCode();

        $context = [
            'http_method' => $method,
            'http_uri'    => $uri,
            'status_code' => $status,
            'duration_ms' => $durationMs,
        ];

        if ($requestId !== '') {
            $context['request_id'] = $requestId;
        }

        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default        => 'info',
        };

        $this->logger->log(
            $level,
            sprintf('HTTP %s %s %d (%.2fms)', $method, $request->getUri()->getPath(), $status, $durationMs),
            $context,
        );

        return $response;
    }
}

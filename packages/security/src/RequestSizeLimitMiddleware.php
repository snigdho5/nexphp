<?php

declare(strict_types=1);

namespace Nex\Security;

use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Http\Responses\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rejects requests with payloads exceeding the configured size limit.
 * Protects against memory exhaustion and DOS via large request bodies.
 */
final class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param int $maxBytes Maximum allowed payload in bytes (default 10 MB)
     */
    public function __construct(
        private readonly int $maxBytes = 10 * 1024 * 1024,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Check Content-Length header first
        $contentLength = $request->getHeaderLine('Content-Length');
        if ($contentLength !== '' && (int) $contentLength > $this->maxBytes) {
            return new JsonResponse([
                'error'     => 'Payload Too Large',
                'message'   => "Request body exceeds the maximum permitted size of {$this->maxBytes} bytes.",
                'max_bytes' => $this->maxBytes,
            ], 413);
        }

        // 2. Check actual stream size if known
        $body = $request->getBody();
        $size = $body->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            return new JsonResponse([
                'error'     => 'Payload Too Large',
                'message'   => "Request body exceeds the maximum permitted size of {$this->maxBytes} bytes.",
                'max_bytes' => $this->maxBytes,
            ], 413);
        }

        return $handler->handle($request);
    }
}

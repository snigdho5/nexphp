<?php

declare(strict_types=1);

namespace Nex\Middleware;

use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param array<string, string> $customHeaders
     */
    public function __construct(array $customHeaders = [])
    {
        $this->headers = array_merge([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '0',
        ], $customHeaders);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}

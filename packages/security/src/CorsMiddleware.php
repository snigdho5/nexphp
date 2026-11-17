<?php

declare(strict_types=1);

namespace Nex\Security;

use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS (Cross-Origin Resource Sharing) middleware.
 *
 * Safe defaults:
 * - Specific origins (or wildcard '*' only when credentials are false)
 * - Strict credentials handling
 * - Handles preflight OPTIONS requests automatically
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins List of allowed origins or ['*']
     * @param list<string> $allowedMethods List of allowed HTTP methods
     * @param list<string> $allowedHeaders List of allowed headers
     * @param list<string> $exposedHeaders List of headers exposed to the browser
     * @param bool $allowCredentials Whether to allow credentials (cookies/auth)
     * @param int $maxAge Preflight cache duration in seconds
     */
    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-Id'],
        private readonly array $exposedHeaders = ['X-Request-Id'],
        private readonly bool $allowCredentials = false,
        private readonly int $maxAge = 86400,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Not a CORS request
        if ($origin === '') {
            return $handler->handle($request);
        }

        // Check if origin is allowed
        $allowedOrigin = $this->resolveAllowedOrigin($origin);
        if ($allowedOrigin === null) {
            // Origin not allowed: handle without CORS headers (browser will block)
            return $handler->handle($request);
        }

        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new Response(204);
            return $this->attachCorsHeaders($response, $allowedOrigin, true);
        }

        $response = $handler->handle($request);
        return $this->attachCorsHeaders($response, $allowedOrigin, false);
    }

    private function resolveAllowedOrigin(string $origin): ?string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            // If credentials are true, cannot use wildcard '*' per CORS spec
            return $this->allowCredentials ? $origin : '*';
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return $origin;
            }
        }

        return null;
    }

    private function attachCorsHeaders(ResponseInterface $response, string $origin, bool $isPreflight): ResponseInterface
    {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($origin !== '*') {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        if (!empty($this->exposedHeaders)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }

        if ($isPreflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }
}

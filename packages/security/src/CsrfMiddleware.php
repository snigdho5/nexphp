<?php

declare(strict_types=1);

namespace Nex\Security;

use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Http\Responses\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF (Cross-Site Request Forgery) protection middleware.
 *
 * Verifies that state-changing requests (POST, PUT, PATCH, DELETE) supply a valid CSRF token.
 * Token can be passed via:
 * - Header: 'X-CSRF-TOKEN' or 'X-XSRF-TOKEN'
 * - Form field: '_token'
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param string|callable():string $tokenOrProvider Token string or callback returning expected token
     * @param list<string> $except URI paths exempt from CSRF verification
     */
    public function __construct(
        private readonly mixed $tokenOrProvider,
        private readonly array $except = [],
    ) {}

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, self::SAFE_METHODS, true) || $this->isExempt($request)) {
            return $handler->handle($request);
        }

        $expected = is_callable($this->tokenOrProvider)
            ? ($this->tokenOrProvider)()
            : (string) $this->tokenOrProvider;

        // Fail-closed: Empty or uninitialized tokens cannot authorize state-changing requests
        if ($expected === '' || trim($expected) === '') {
            return new JsonResponse([
                'error'   => 'CSRF verification failed',
                'message' => 'CSRF verification failed. The expected token is uninitialized or empty.',
            ], 403);
        }

        $provided = $this->extractToken($request);

        if ($provided === null || $provided === '' || !hash_equals($expected, $provided)) {
            return new JsonResponse([
                'error'   => 'CSRF token mismatch',
                'message' => 'CSRF verification failed. The provided token is invalid or missing.',
            ], 403);
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('X-CSRF-TOKEN');
        if ($header !== '') {
            return $header;
        }

        $xsrf = $request->getHeaderLine('X-XSRF-TOKEN');
        if ($xsrf !== '') {
            return $xsrf;
        }

        $parsed = $request->getParsedBody();
        if (is_array($parsed) && isset($parsed['_token']) && is_string($parsed['_token'])) {
            return $parsed['_token'];
        }

        return null;
    }

    private function isExempt(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        foreach ($this->except as $pattern) {
            if ($pattern === $path || (str_ends_with($pattern, '*') && str_starts_with($path, rtrim($pattern, '*')))) {
                return true;
            }
        }

        return false;
    }
}

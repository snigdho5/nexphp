<?php

declare(strict_types=1);

namespace Nex\Security;

use Nex\Contracts\Http\MiddlewareInterface;
use Nex\Http\Responses\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate limiting middleware using RateLimiter.
 *
 * Automatically generates rate limit keys by IP or authenticated user.
 * Attaches standard rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining).
 * Returns HTTP 429 Too Many Requests when limit is exceeded.
 */
final class ThrottleRequestsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
        private readonly ?string $prefix = 'throttle',
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts, $this->decaySeconds)) {
            $retryAfter = $this->limiter->availableIn($key, $this->decaySeconds);

            return (new JsonResponse([
                'error'       => 'Too Many Requests',
                'message'     => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429))
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $this->limiter->hit($key, $this->decaySeconds);

        $response = $handler->handle($request);

        $remaining = $this->limiter->remaining($key, $this->maxAttempts, $this->decaySeconds);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function resolveRequestSignature(ServerRequestInterface $request): string
    {
        // Use client IP or fallback
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';

        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            $ip = trim($parts[0]);
        }

        $route = $request->getUri()->getPath();

        return ($this->prefix !== null ? $this->prefix . ':' : '') . sha1($ip . '|' . $route);
    }
}

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
    /**
     * @param RateLimiter $limiter
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @param string|null $prefix
     * @param list<string>|bool $trustedProxies True to trust all proxies, false to trust none, or list of trusted proxy IPs
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
        private readonly ?string $prefix = 'throttle',
        private readonly array|bool $trustedProxies = false,
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
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
        $ip = $remoteAddr;

        // Only inspect X-Forwarded-For if proxy is trusted
        if ($this->isProxyTrusted($remoteAddr)) {
            $forwarded = $request->getHeaderLine('X-Forwarded-For');
            if ($forwarded !== '') {
                $parts = explode(',', $forwarded);
                $ip = trim($parts[0]);
            }
        }

        $route = $request->getUri()->getPath();

        return ($this->prefix !== null ? $this->prefix . ':' : '') . sha1($ip . '|' . $route);
    }

    private function isProxyTrusted(string $remoteAddr): bool
    {
        if ($this->trustedProxies === true) {
            return true;
        }

        if (is_array($this->trustedProxies)) {
            return in_array($remoteAddr, $this->trustedProxies, true);
        }

        return false;
    }
}

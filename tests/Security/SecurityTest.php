<?php

declare(strict_types=1);

namespace Nex\Tests\Security;

use Nex\Contracts\Http\RequestHandlerInterface;
use Nex\Http\Response;
use Nex\Http\ServerRequest;
use Nex\Security\CorsMiddleware;
use Nex\Security\CsrfMiddleware;
use Nex\Security\RateLimiter;
use Nex\Security\RequestSizeLimitMiddleware;
use Nex\Security\ThrottleRequestsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SecurityTest extends TestCase
{
    private function createHandler(int $statusCode = 200, string $body = 'OK'): RequestHandlerInterface
    {
        return new class($statusCode, $body) implements RequestHandlerInterface {
            public function __construct(private int $statusCode, private string $body) {}
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new Response($this->statusCode, ['Content-Type' => 'text/plain'], $this->body);
            }
        };
    }

    // -------------------------------------------------------------------------
    // CORS Tests
    // -------------------------------------------------------------------------

    public function test_cors_preflight_request(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST'],
            allowCredentials: true,
        );

        $request = new ServerRequest(
            method: 'OPTIONS',
            uri: '/api/data',
            headers: [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'POST',
            ],
        );

        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function test_cors_disallowed_origin_does_not_attach_headers(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $request = new ServerRequest('GET', '/api/data', headers: ['Origin' => 'https://evil.com']);

        $response = $middleware->process($request, $this->createHandler());

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_cors_wildcard_origin(): void
    {
        $middleware = new CorsMiddleware(allowedOrigins: ['*'], allowCredentials: false);
        $request = new ServerRequest('GET', '/api/data', headers: ['Origin' => 'https://any-domain.com']);

        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // CSRF Tests
    // -------------------------------------------------------------------------

    public function test_csrf_allows_safe_methods_without_token(): void
    {
        $middleware = new CsrfMiddleware('expected_token');
        $request = new ServerRequest('GET', '/profile');

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_blocks_state_changing_methods_without_token(): void
    {
        $middleware = new CsrfMiddleware('expected_token');
        $request = new ServerRequest('POST', '/profile');

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_csrf_allows_post_with_valid_header(): void
    {
        $token = CsrfMiddleware::generateToken();
        $middleware = new CsrfMiddleware($token);
        $request = new ServerRequest('POST', '/profile', headers: ['X-CSRF-TOKEN' => $token]);

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_allows_post_with_valid_body_token(): void
    {
        $token = 'my_secret_token';
        $middleware = new CsrfMiddleware($token);
        $request = (new ServerRequest('POST', '/profile'))->withParsedBody(['_token' => $token]);

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_exempt_routes(): void
    {
        $middleware = new CsrfMiddleware('expected_token', except: ['/webhook/*']);
        $request = new ServerRequest('POST', '/webhook/stripe');

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Rate Limiter & Throttle Tests
    // -------------------------------------------------------------------------

    public function test_rate_limiter_tracking_and_decay(): void
    {
        $limiter = new RateLimiter();
        $key = 'user:1';

        $this->assertSame(1, $limiter->hit($key, 60));
        $this->assertSame(2, $limiter->hit($key, 60));
        $this->assertSame(2, $limiter->attempts($key));
        $this->assertSame(3, $limiter->remaining($key, 5, 60));
        $this->assertFalse($limiter->tooManyAttempts($key, 5, 60));

        $limiter->reset($key);
        $this->assertSame(0, $limiter->attempts($key));
    }

    public function test_throttle_middleware_enforces_limit(): void
    {
        $limiter = new RateLimiter();
        $middleware = new ThrottleRequestsMiddleware($limiter, maxAttempts: 2, decaySeconds: 60);
        $request = new ServerRequest('GET', '/api/resource', serverParams: ['REMOTE_ADDR' => '192.168.1.1']);

        // First request: OK
        $res1 = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertSame('2', $res1->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('1', $res1->getHeaderLine('X-RateLimit-Remaining'));

        // Second request: OK
        $res2 = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertSame('0', $res2->getHeaderLine('X-RateLimit-Remaining'));

        // Third request: 429 Too Many Requests
        $res3 = $middleware->process($request, $this->createHandler());
        $this->assertSame(429, $res3->getStatusCode());
        $this->assertTrue($res3->hasHeader('Retry-After'));
    }

    // -------------------------------------------------------------------------
    // Request Size Limit Tests
    // -------------------------------------------------------------------------

    public function test_request_size_limit_allows_small_payload(): void
    {
        $middleware = new RequestSizeLimitMiddleware(maxBytes: 1024);
        $request = new ServerRequest('POST', '/upload', headers: ['Content-Length' => '500'], body: str_repeat('a', 500));

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_request_size_limit_rejects_large_payload(): void
    {
        $middleware = new RequestSizeLimitMiddleware(maxBytes: 100);
        $request = new ServerRequest('POST', '/upload', headers: ['Content-Length' => '500']);

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(413, $response->getStatusCode());
    }
}

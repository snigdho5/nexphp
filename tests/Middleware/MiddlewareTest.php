<?php

declare(strict_types=1);

namespace Nex\Tests\Middleware;

use Exception;
use Nex\Http\Response;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Middleware\ExceptionHandlingMiddleware;
use Nex\Middleware\Pipeline;
use Nex\Middleware\RequestIdMiddleware;
use Nex\Middleware\SecurityHeadersMiddleware;
use Nex\Routing\Exceptions\RouteNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareTest extends TestCase
{
    public function testPipelineExecutionOrder(): void
    {
        $log = [];

        $m1 = new class($log) implements \Nex\Contracts\Http\MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->log[] = 'm1_in';
                $res = $handler->handle($request);
                $this->log[] = 'm1_out';
                return $res;
            }
        };

        $m2 = new class($log) implements \Nex\Contracts\Http\MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->log[] = 'm2_in';
                $res = $handler->handle($request);
                $this->log[] = 'm2_out';
                return $res;
            }
        };

        $fallback = new class($log) implements RequestHandlerInterface {
            public function __construct(private array &$log)
            {
            }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->log[] = 'handler';
                return new JsonResponse(['ok' => true]);
            }
        };

        $pipeline = new Pipeline([$m1, $m2], $fallback);
        $pipeline->handle(new ServerRequest('GET', '/'));

        $this->assertSame(['m1_in', 'm2_in', 'handler', 'm2_out', 'm1_out'], $log);
    }

    public function testRequestIdMiddleware(): void
    {
        $middleware = new RequestIdMiddleware();
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['id' => $request->getAttribute('request_id')]);
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        $this->assertTrue($response->hasHeader('X-Request-Id'));
        $headerId = $response->getHeaderLine('X-Request-Id');
        $this->assertNotEmpty($headerId);
        $this->assertSame('{"id":"' . $headerId . '"}', (string) $response->getBody());
    }

    public function testSecurityHeadersMiddleware(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testExceptionHandlingMiddlewareCatches404And500(): void
    {
        $middleware = new ExceptionHandlingMiddleware(debug: false);

        // 404 test
        $h404 = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RouteNotFoundException('/missing', 'GET');
            }
        };

        $reqJson = (new ServerRequest('GET', '/api/missing'))->withHeader('Accept', 'application/json');
        $res404 = $middleware->process($reqJson, $h404);

        $this->assertSame(404, $res404->getStatusCode());
        $this->assertStringContainsString('"code":404', (string) $res404->getBody());

        // 500 test
        $h500 = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new Exception('Sensitive database connection failure');
            }
        };

        $res500 = $middleware->process($reqJson, $h500);
        $this->assertSame(500, $res500->getStatusCode());
        // In non-debug mode, sensitive message is hidden
        $this->assertStringContainsString('Internal Server Error', (string) $res500->getBody());
        $this->assertStringNotContainsString('Sensitive database connection failure', (string) $res500->getBody());
    }
}

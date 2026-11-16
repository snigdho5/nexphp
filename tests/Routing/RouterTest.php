<?php

declare(strict_types=1);

namespace Nex\Tests\Routing;

use Nex\Container\Container;
use Nex\Http\ServerRequest;
use Nex\Routing\Attributes\Get;
use Nex\Routing\Attributes\Post;
use Nex\Routing\Attributes\Route as RouteAttr;
use Nex\Routing\Exceptions\MethodNotAllowedException;
use Nex\Routing\Exceptions\RouteNotFoundException;
use Nex\Routing\Router;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $container = new Container();
        $this->router = new Router($container);
    }

    public function testRouteMatchingAndParameterExtraction(): void
    {
        $this->router->get('/users/{id}', function (string $id, ServerRequestInterface $request) {
            return ['user_id' => (int) $id, 'method' => $request->getMethod()];
        });

        $request = new ServerRequest('GET', '/users/99');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"user_id":99,"method":"GET"}', (string) $response->getBody());
    }

    public function testRouteConstraintMismatchReturns404(): void
    {
        $this->router->get('/items/{id}', fn(string $id) => ['item' => $id])
            ->where('id', '[0-9]+');

        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch(new ServerRequest('GET', '/items/abc'));
    }

    public function testMethodNotAllowedThrows405(): void
    {
        $this->router->post('/submit', fn() => ['saved' => true]);

        $this->expectException(MethodNotAllowedException::class);
        $this->router->dispatch(new ServerRequest('GET', '/submit'));
    }

    public function testRouteGroups(): void
    {
        $this->router->group(['prefix' => '/api/v1'], function (Router $router) {
            $router->get('/ping', fn() => ['status' => 'pong']);
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/api/v1/ping'));
        $this->assertSame('{"status":"pong"}', (string) $response->getBody());
    }

    public function testAttributeBasedControllerRegistration(): void
    {
        $this->router->registerController(SampleApiController::class);

        $responseList = $this->router->dispatch(new ServerRequest('GET', '/api/articles'));
        $this->assertSame('{"articles":["first","second"]}', (string) $responseList->getBody());

        $responseCreate = $this->router->dispatch(new ServerRequest('POST', '/api/articles'));
        $this->assertSame('{"created":true}', (string) $responseCreate->getBody());
    }
}

#[RouteAttr(path: '/api')]
class SampleApiController
{
    #[Get(path: '/articles')]
    public function index(): array
    {
        return ['articles' => ['first', 'second']];
    }

    #[Post(path: '/articles')]
    public function store(): array
    {
        return ['created' => true];
    }
}

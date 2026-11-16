<?php

declare(strict_types=1);

namespace Nex\Tests\Http;

use Nex\Http\Responses\EmptyResponse;
use Nex\Http\Responses\HtmlResponse;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\Responses\RedirectResponse;
use Nex\Http\Responses\TextResponse;
use Nex\Http\ServerRequest;
use Nex\Http\Stream;
use Nex\Http\Uri;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function testStreamReadWrite(): void
    {
        $stream = new Stream('hello world');
        $this->assertSame(11, $stream->getSize());
        $this->assertSame('hello world', (string) $stream);

        $stream->write('!');
        $this->assertSame('hello world!', (string) $stream);
    }

    public function testUriComposition(): void
    {
        $uri = new Uri('https://api.example.com:8080/v1/users?page=1#profile');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('api.example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/v1/users', $uri->getPath());
        $this->assertSame('page=1', $uri->getQuery());
        $this->assertSame('profile', $uri->getFragment());
        $this->assertSame('api.example.com:8080', $uri->getAuthority());
    }

    public function testJsonResponse(): void
    {
        $response = new JsonResponse(['message' => 'success', 'code' => 200]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"message":"success","code":200}', (string) $response->getBody());
    }

    public function testRedirectResponse(): void
    {
        $response = new RedirectResponse('/login', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testEmptyResponse(): void
    {
        $response = new EmptyResponse(204);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testServerRequestAttributes(): void
    {
        $request = new ServerRequest('GET', '/users/42');
        $request = $request->withAttribute('user_id', 42);

        $this->assertSame(42, $request->getAttribute('user_id'));
        $this->assertNull($request->getAttribute('non_existent'));
    }
}

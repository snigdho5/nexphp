<?php

declare(strict_types=1);

namespace Nex\Testing;

use Nex\Foundation\Application;
use Nex\Http\ServerRequest;
use Nex\Http\Stream;
use Psr\Http\Message\ServerRequestInterface;

class TestClient
{
    public function __construct(private Application $app)
    {
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, headers: $headers);
    }

    /**
     * @param array<string, mixed>|string $data
     * @param array<string, string|list<string>> $headers
     */
    public function post(string $uri, array|string $data = [], array $headers = []): TestResponse
    {
        return $this->requestWithPayload('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed>|string $data
     * @param array<string, string|list<string>> $headers
     */
    public function put(string $uri, array|string $data = [], array $headers = []): TestResponse
    {
        return $this->requestWithPayload('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function delete(string $uri, array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, headers: $headers);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function request(string $method, string $uri, array $headers = [], string $body = ''): TestResponse
    {
        $request = new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: new Stream($body)
        );

        return $this->dispatch($request);
    }

    public function dispatch(ServerRequestInterface $request): TestResponse
    {
        $response = $this->app->handle($request);
        $this->app->terminate($request, $response);

        return new TestResponse($response);
    }

    /**
     * @param array<string, mixed>|string $data
     * @param array<string, string|list<string>> $headers
     */
    private function requestWithPayload(string $method, string $uri, array|string $data, array $headers): TestResponse
    {
        $body = '';
        if (is_array($data)) {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] ??= 'application/json';
        } else {
            $body = $data;
        }

        $headers['Accept'] ??= 'application/json';

        return $this->request($method, $uri, $headers, $body);
    }
}

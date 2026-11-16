<?php

declare(strict_types=1);

namespace Nex\Http;

use Psr\Http\Message\ServerRequestInterface;

class ServerRequestFactory
{
    /**
     * Create a new ServerRequest instance from current PHP superglobals.
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = self::createUriFromGlobals($server);
        $headers = self::extractHeadersFromGlobals($server);
        $body = new Stream(fopen('php://input', 'r') ?: '');

        $protocol = '1.1';
        if (isset($server['SERVER_PROTOCOL']) && preg_match('#^HTTP/(\d+\.\d+)$#i', $server['SERVER_PROTOCOL'], $matches)) {
            $protocol = $matches[1];
        }

        $request = new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $body,
            version: $protocol,
            serverParams: $server
        );

        $request = $request
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET);

        // Auto-parse body for JSON or standard POST
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $contents = (string) $body;
            if ($contents !== '') {
                $decoded = json_decode($contents, associative: true);
                if (is_array($decoded)) {
                    $request = $request->withParsedBody($decoded);
                }
            }
        } elseif (!empty($_POST)) {
            $request = $request->withParsedBody($_POST);
        }

        return $request;
    }

    private static function createUriFromGlobals(array $server): Uri
    {
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ||
                  (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == 443) ? 'https' : 'http';

        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';
        $port = null;

        if (str_contains($host, ':')) {
            [$host, $portString] = explode(':', $host, 2);
            $port = (int) $portString;
        } elseif (isset($server['SERVER_PORT'])) {
            $port = (int) $server['SERVER_PORT'];
        }

        $requestUri = $server['REQUEST_URI'] ?? '/';
        $uriString = $scheme . '://' . $host . ($port ? ':' . $port : '') . $requestUri;

        return new Uri($uriString);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function extractHeadersFromGlobals(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = [(string) $value];
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = [(string) $value];
            }
        }

        return $headers;
    }
}

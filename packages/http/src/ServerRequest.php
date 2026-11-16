<?php

declare(strict_types=1);

namespace Nex\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest implements ServerRequestInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $headers = [];

    /**
     * @var array<string, string>
     */
    private array $headerNames = [];

    private string $method;
    private UriInterface $uri;
    private StreamInterface $stream;
    private string $protocolVersion = '1.1';
    private ?string $requestTarget = null;

    /**
     * @var array<string, mixed>
     */
    private array $serverParams;

    /**
     * @var array<string, string>
     */
    private array $cookieParams = [];

    /**
     * @var array<string, mixed>
     */
    private array $queryParams = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @var array<string, mixed>|object|null
     */
    private array|object|null $parsedBody = null;

    /**
     * @var array<mixed>
     */
    private array $uploadedFiles = [];

    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $serverParams
     * @param StreamInterface|string $body
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        StreamInterface|string $body = '',
        string $version = '1.1',
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->stream = $body instanceof StreamInterface ? $body : new Stream($body);
        $this->protocolVersion = $version;
        $this->serverParams = $serverParams;

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $lower = strtolower($name);
        if (!isset($this->headerNames[$lower])) {
            return [];
        }

        return $this->headers[$this->headerNames[$lower]];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $lower = strtolower($name);
        $values = is_array($value) ? array_values($value) : [(string) $value];

        if (isset($clone->headerNames[$lower])) {
            $origName = $clone->headerNames[$lower];
            $clone->headers[$origName] = array_merge($clone->headers[$origName], $values);
        } else {
            $clone->setHeader($name, $values);
        }

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $lower = strtolower($name);

        if (isset($clone->headerNames[$lower])) {
            $origName = $clone->headerNames[$lower];
            unset($clone->headers[$origName], $clone->headerNames[$lower]);
        }

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->stream;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->stream = $body;
        return $clone;
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            if ($uri->getHost() !== '') {
                $host = $uri->getHost();
                if ($uri->getPort() !== null) {
                    $host .= ':' . $uri->getPort();
                }
                $clone->setHeader('Host', $host);
            }
        }

        return $clone;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    public function withParsedBody(mixed $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    private function setHeader(string $name, mixed $value): void
    {
        $lower = strtolower($name);
        $values = is_array($value) ? array_values($value) : [(string) $value];

        if (isset($this->headerNames[$lower])) {
            unset($this->headers[$this->headerNames[$lower]]);
        }

        $this->headerNames[$lower] = $name;
        $this->headers[$name] = $values;
    }
}

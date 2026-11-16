<?php

declare(strict_types=1);

namespace Nex\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    /**
     * @var array<string, list<string>>
     */
    protected array $headers = [];

    /**
     * Map of lowercase header name to original header name
     * @var array<string, string>
     */
    protected array $headerNames = [];

    protected int $statusCode;
    protected string $reasonPhrase;
    protected StreamInterface $stream;
    protected string $protocolVersion = '1.1';

    private const array PHRASES = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * @param StreamInterface|string $body
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        StreamInterface|string $body = '',
        string $version = '1.1',
        string $reason = ''
    ) {
        $this->statusCode = $status;
        $this->protocolVersion = $version;
        $this->reasonPhrase = $reason !== '' ? $reason : (self::PHRASES[$status] ?? '');
        $this->stream = $body instanceof StreamInterface ? $body : new Stream($body);

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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::PHRASES[$code] ?? '');
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    protected function setHeader(string $name, mixed $value): void
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

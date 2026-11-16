<?php

declare(strict_types=1);

namespace Nex\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts !== false) {
                $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
                $this->userInfo = $parts['user'] ?? '';
                if (isset($parts['pass'])) {
                    $this->userInfo .= ':' . $parts['pass'];
                }
                $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
                $this->port = $parts['port'] ?? null;
                $this->path = $parts['path'] ?? '';
                $this->query = $parts['query'] ?? '';
                $this->fragment = $parts['fragment'] ?? '';
            }
        }
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '' || $this->scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;
        if ($authority !== '' && $path !== '' && !str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null && !$this->isStandardPort($this->scheme, $this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->scheme = strtolower($scheme);
        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $info = $user;
        if ($password !== null && $password !== '') {
            $info .= ':' . $password;
        }

        $clone = clone $this;
        $clone->userInfo = $info;
        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->host = strtolower($host);
        return $clone;
    }

    public function withPort(?int $port): static
    {
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->query = ltrim($query, '?');
        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->fragment = ltrim($fragment, '#');
        return $clone;
    }

    private function isStandardPort(string $scheme, int $port): bool
    {
        return match ($scheme) {
            'http' => $port === 80,
            'https' => $port === 443,
            default => false,
        };
    }
}

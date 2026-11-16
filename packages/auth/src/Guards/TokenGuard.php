<?php

declare(strict_types=1);

namespace Nex\Auth\Guards;

use Nex\Auth\Contracts\AuthenticatableInterface;
use Nex\Auth\Contracts\GuardInterface;
use Nex\Auth\Contracts\UserProviderInterface;
use Nex\Container\Container;
use Psr\Http\Message\ServerRequestInterface;

class TokenGuard implements GuardInterface
{
    private ?AuthenticatableInterface $user = null;
    private ?string $lastToken = null;

    public function __construct(
        private UserProviderInterface $provider,
        private ?ServerRequestInterface $request = null,
        private string $tokenField = 'api_token'
    ) {
    }

    public function setRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;
        $this->user = null;
        $this->lastToken = null;
        return $this;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        $request = $this->resolveRequest();
        $token = $this->extractTokenFromRequest($request);

        if ($token === null || $token === '') {
            $this->user = null;
            $this->lastToken = null;
            return null;
        }

        if ($this->user !== null && $this->lastToken === $token) {
            return $this->user;
        }

        $this->lastToken = $token;
        return $this->user = $this->provider->retrieveByCredentials([$this->tokenField => $token]);
    }

    public function id(): string|int|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function setUser(AuthenticatableInterface $user): void
    {
        $this->user = $user;
    }

    public function extractToken(): ?string
    {
        return $this->extractTokenFromRequest($this->resolveRequest());
    }

    private function resolveRequest(): ?ServerRequestInterface
    {
        if (Container::getInstance()->has(ServerRequestInterface::class)) {
            return Container::getInstance()->make(ServerRequestInterface::class);
        }

        return $this->request;
    }

    private function extractTokenFromRequest(?ServerRequestInterface $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $params = $request->getQueryParams();
        return $params[$this->tokenField] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Nex\Auth;

use Nex\Auth\Contracts\AuthManagerInterface;
use Nex\Auth\Contracts\GuardInterface;
use Nex\Auth\Exceptions\AuthenticationException;
use Nex\Auth\Guards\TokenGuard;
use Nex\Contracts\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManagerInterface|GuardInterface $auth,
        private ?string $guardName = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $guard = $this->auth instanceof AuthManagerInterface
            ? $this->auth->guard($this->guardName)
            : $this->auth;

        if ($guard instanceof TokenGuard) {
            $guard->setRequest($request);
        }

        if ($guard->guest()) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $request = $request->withAttribute('user', $guard->user());

        return $handler->handle($request);
    }
}

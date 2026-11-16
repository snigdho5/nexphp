<?php

declare(strict_types=1);

namespace Nex\Runtime\Fpm;

use Nex\Contracts\Foundation\ApplicationInterface;
use Nex\Http\ResponseEmitter;
use Nex\Http\ServerRequestFactory;
use Nex\Runtime\Contracts\RunnerInterface;
use Psr\Http\Message\ServerRequestInterface;

class FpmRunner implements RunnerInterface
{
    public function __construct(
        private ?ResponseEmitter $emitter = null,
        private ?ServerRequestInterface $request = null
    ) {
        $this->emitter ??= new ResponseEmitter();
    }

    public function run(ApplicationInterface $app): void
    {
        $request = $this->request ?? ServerRequestFactory::fromGlobals();
        $response = $app->handle($request);

        $this->emitter->emit($response);

        $app->terminate($request, $response);
    }
}

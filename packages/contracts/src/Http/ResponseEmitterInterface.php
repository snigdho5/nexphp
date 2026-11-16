<?php

declare(strict_types=1);

namespace Nex\Contracts\Http;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    /**
     * Emit headers and body of the HTTP response.
     */
    public function emit(ResponseInterface $response): void;
}

<?php

declare(strict_types=1);

namespace Nex\Http;

use Nex\Contracts\Http\ResponseEmitterInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseEmitter implements ResponseEmitterInterface
{
    public function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            // Send status line
            $version = $response->getProtocolVersion();
            $status = $response->getStatusCode();
            $reason = $response->getReasonPhrase();

            header("HTTP/$version $status $reason", replace: true, response_code: $status);

            // Send headers
            foreach ($response->getHeaders() as $name => $values) {
                $first = true;
                foreach ($values as $value) {
                    header("$name: $value", replace: $first);
                    $first = false;
                }
            }
        }

        // Stream body
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk !== '') {
                echo $chunk;
            }
        }
    }
}

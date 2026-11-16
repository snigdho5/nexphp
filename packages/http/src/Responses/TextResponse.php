<?php

declare(strict_types=1);

namespace Nex\Http\Responses;

use Nex\Http\Response;

class TextResponse extends Response
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(string $text, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'text/plain; charset=utf-8';
        parent::__construct(status: $status, headers: $headers, body: $text);
    }
}

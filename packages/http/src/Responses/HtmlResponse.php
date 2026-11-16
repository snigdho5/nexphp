<?php

declare(strict_types=1);

namespace Nex\Http\Responses;

use Nex\Http\Response;

class HtmlResponse extends Response
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(string $html, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        parent::__construct(status: $status, headers: $headers, body: $html);
    }
}

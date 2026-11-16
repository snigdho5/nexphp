<?php

declare(strict_types=1);

namespace Nex\Http\Responses;

use Nex\Http\Response;

class RedirectResponse extends Response
{
    /**
     * @param string $url
     * @param int $status
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        $headers['Location'] = $url;
        parent::__construct(status: $status, headers: $headers, body: '');
    }
}

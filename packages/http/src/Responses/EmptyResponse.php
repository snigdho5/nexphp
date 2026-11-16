<?php

declare(strict_types=1);

namespace Nex\Http\Responses;

use Nex\Http\Response;

class EmptyResponse extends Response
{
    /**
     * @param int $status
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(int $status = 204, array $headers = [])
    {
        parent::__construct(status: $status, headers: $headers, body: '');
    }
}

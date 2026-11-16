<?php

declare(strict_types=1);

namespace Nex\Http\Responses;

use InvalidArgumentException;
use Nex\Http\Response;

class JsonResponse extends Response
{
    /**
     * @param mixed $data
     * @param int $status
     * @param array<string, string|list<string>> $headers
     * @param int $encodingOptions
     */
    public function __construct(
        mixed $data,
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) {
        $json = json_encode($data, $encodingOptions);
        if ($json === false) {
            throw new InvalidArgumentException('Unable to encode data to JSON: ' . json_last_error_msg());
        }

        $headers['Content-Type'] = 'application/json';

        parent::__construct(status: $status, headers: $headers, body: $json);
    }
}

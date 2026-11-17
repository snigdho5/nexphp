<?php

declare(strict_types=1);

namespace Nex\HttpClient\Contracts;

use Nex\Concurrency\Contracts\TaskInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): ResponseInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $url, mixed $body = null, array $options = []): ResponseInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function requestAsync(string $method, string $url, array $options = []): TaskInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function getAsync(string $url, array $options = []): TaskInterface;

    /**
     * @param array<string, mixed> $options
     */
    public function postAsync(string $url, mixed $body = null, array $options = []): TaskInterface;
}

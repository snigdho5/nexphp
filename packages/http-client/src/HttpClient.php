<?php

declare(strict_types=1);

namespace Nex\HttpClient;

use CurlMultiHandle;
use Fiber;
use Nex\Concurrency\Contracts\TaskInterface;
use Nex\Concurrency\Task;
use Nex\Http\Response;
use Nex\HttpClient\Contracts\ClientInterface;
use Nex\HttpClient\Exceptions\HttpClientException;
use Psr\Http\Message\ResponseInterface;

class HttpClient implements ClientInterface
{
    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(private array $defaultOptions = [])
    {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $options = array_merge($this->defaultOptions, $options);
        $method = strtoupper($method);

        // If inside a Fiber, execute cooperatively without blocking other tasks
        if (Fiber::getCurrent() !== null) {
            return $this->executeAsyncCurl($method, $url, $options);
        }

        return $this->executeSyncCurl($method, $url, $options);
    }

    public function get(string $url, array $options = []): ResponseInterface
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, mixed $body = null, array $options = []): ResponseInterface
    {
        if ($body !== null) {
            $options['body'] = $body;
        }
        return $this->request('POST', $url, $options);
    }

    public function requestAsync(string $method, string $url, array $options = []): TaskInterface
    {
        return new Task(fn() => $this->request($method, $url, $options));
    }

    public function getAsync(string $url, array $options = []): TaskInterface
    {
        return $this->requestAsync('GET', $url, $options);
    }

    public function postAsync(string $url, mixed $body = null, array $options = []): TaskInterface
    {
        if ($body !== null) {
            $options['body'] = $body;
        }
        return $this->requestAsync('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeSyncCurl(string $method, string $url, array $options): ResponseInterface
    {
        $ch = $this->prepareCurlHandle($method, $url, $options);
        $rawHeaders = '';

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $header) use (&$rawHeaders) {
            $rawHeaders .= $header;
            return strlen($header);
        });

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            throw new HttpClientException("HTTP request to [$url] failed: $error");
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $this->buildResponse($statusCode, $rawHeaders, (string) $body);
    }

    /**
     * Cooperatively executes non-blocking cURL using Fiber suspension.
     *
     * @param array<string, mixed> $options
     */
    private function executeAsyncCurl(string $method, string $url, array $options): ResponseInterface
    {
        $ch = $this->prepareCurlHandle($method, $url, $options);
        $rawHeaders = '';

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $header) use (&$rawHeaders) {
            $rawHeaders .= $header;
            return strlen($header);
        });

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $active = 0;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active > 0) {
                // Cooperative yield!
                Fiber::suspend();
            }
        } while ($active > 0 && $status === CURLM_OK);

        $body = curl_multi_getcontent($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);

        return $this->buildResponse($statusCode, $rawHeaders, (string) $body);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function prepareCurlHandle(string $method, string $url, array $options): \CurlHandle
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new HttpClientException("Unable to initialize curl for [$url]");
        }

        $timeout = (float) ($options['timeout'] ?? 10.0);
        $connectTimeout = (float) ($options['connect_timeout'] ?? 5.0);
        $verifyTls = (bool) ($options['verify'] ?? true);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ceil($timeout));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) ceil($connectTimeout));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyTls);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, (bool) ($options['follow_redirects'] ?? true));

        // Setup headers
        $headers = [];
        foreach (($options['headers'] ?? []) as $name => $val) {
            $headers[] = "$name: $val";
        }

        // Setup payload
        if (isset($options['body'])) {
            $payload = $options['body'];
            if (is_array($payload)) {
                $payload = json_encode($payload);
                $headers[] = 'Content-Type: application/json';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $ch;
    }

    private function buildResponse(int $statusCode, string $rawHeaders, string $body): ResponseInterface
    {
        $headers = [];
        $lines = explode("\r\n", $rawHeaders);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)][] = trim($value);
            }
        }

        return new Response(status: $statusCode, headers: $headers, body: $body);
    }
}

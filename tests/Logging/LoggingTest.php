<?php

declare(strict_types=1);

namespace Nex\Tests\Logging;

use Nex\Contracts\Http\RequestHandlerInterface;
use Nex\Http\Response;
use Nex\Http\ServerRequest;
use Nex\Logging\HttpLoggingMiddleware;
use Nex\Logging\JsonFormatter;
use Nex\Logging\Redactor;
use Nex\Logging\StructuredLogger;
use Nex\Logging\TextFormatter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class LoggingTest extends TestCase
{
    public function test_redactor_masks_sensitive_keys(): void
    {
        $redactor = new Redactor();

        $data = [
            'username'     => 'alice',
            'password'     => 'secret123',
            'nested'       => [
                'access_token' => 'jwt.token.here',
                'public_data'  => 'visible',
            ],
            'api_key'      => 'key_xyz',
        ];

        $redacted = $redactor->redact($data);

        $this->assertSame('alice', $redacted['username']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['nested']['access_token']);
        $this->assertSame('visible', $redacted['nested']['public_data']);
        $this->assertSame('[REDACTED]', $redacted['api_key']);
    }

    public function test_json_formatter_produces_valid_json(): void
    {
        $formatter = new JsonFormatter();
        $output = $formatter->format(
            'info',
            'User logged in',
            ['user_id' => 42, 'exception' => new RuntimeException('Sample error')],
            new \DateTimeImmutable('2026-01-01 12:00:00Z'),
        );

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('User logged in', $decoded['message']);
        $this->assertSame(42, $decoded['context']['user_id']);
        $this->assertSame(RuntimeException::class, $decoded['context']['exception']['class']);
        $this->assertSame('Sample error', $decoded['context']['exception']['message']);
    }

    public function test_text_formatter_output(): void
    {
        $formatter = new TextFormatter();
        $output = $formatter->format(
            'error',
            'Something broke',
            ['key' => 'val'],
            new \DateTimeImmutable('2026-01-01 12:00:00'),
        );

        $this->assertStringContainsString('ERROR', $output);
        $this->assertStringContainsString('Something broke', $output);
        $this->assertStringContainsString('"key":"val"', $output);
    }

    public function test_structured_logger_records_and_redacts(): void
    {
        $logger = new StructuredLogger();
        $logger->info('User {name} authenticated', [
            'name'     => 'Bob',
            'password' => 'secret_pass',
        ]);

        $records = $logger->getRecords();
        $this->assertCount(1, $records);

        $entry = json_decode($records[0], true);
        $this->assertSame('User Bob authenticated', $entry['message']);
        $this->assertSame('Bob', $entry['context']['name']);
        $this->assertSame('[REDACTED]', $entry['context']['password']);
    }

    public function test_structured_logger_global_context(): void
    {
        $logger = (new StructuredLogger())->withContext(['request_id' => 'req-123']);
        $logger->warning('Resource missing', ['id' => 99]);

        $entry = json_decode($logger->getRecords()[0], true);
        $this->assertSame('req-123', $entry['context']['request_id']);
        $this->assertSame(99, $entry['context']['id']);
    }

    public function test_http_logging_middleware(): void
    {
        $logger = new StructuredLogger();
        $middleware = new HttpLoggingMiddleware($logger);

        $request = new ServerRequest('GET', '/api/users', headers: ['X-Request-Id' => 'req-abc']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $records = $logger->getRecords();
        $this->assertCount(1, $records);

        $log = json_decode($records[0], true);
        $this->assertSame('info', $log['level']);
        $this->assertSame('GET', $log['context']['http_method']);
        $this->assertSame(200, $log['context']['status_code']);
        $this->assertSame('req-abc', $log['context']['request_id']);
        $this->assertArrayHasKey('duration_ms', $log['context']);
    }
}

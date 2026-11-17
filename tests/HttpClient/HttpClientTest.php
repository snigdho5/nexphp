<?php

declare(strict_types=1);

namespace Nex\Tests\HttpClient;

use Nex\Concurrency\Scheduler;
use Nex\HttpClient\Exceptions\HttpClientException;
use Nex\HttpClient\HttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    private HttpClient $client;

    protected function setUp(): void
    {
        $this->client = new HttpClient([
            'timeout' => 1.0,
            'connect_timeout' => 1.0,
        ]);
    }

    public function testConnectionFailureThrowsHttpClientException(): void
    {
        $this->expectException(HttpClientException::class);
        $this->client->get('http://127.0.0.1:54321/unreachable');
    }

    public function testAsyncTaskCreation(): void
    {
        $task = $this->client->getAsync('http://127.0.0.1:54321/async');

        $this->assertFalse($task->isComplete());
        $this->assertFalse($task->isCancelled());
    }

    public function testSchedulerIntegrationWithAsyncClient(): void
    {
        $scheduler = new Scheduler();

        $t1 = $this->client->getAsync('http://127.0.0.1:54321/task1');
        $t2 = $this->client->getAsync('http://127.0.0.1:54321/task2');

        try {
            $scheduler->all([$t1, $t2]);
        } catch (HttpClientException $e) {
            // Expected connection failure on unreachable port
            $this->assertStringContainsString('failed', $e->getMessage());
        }

        $this->assertTrue($t1->isComplete() || $t1->isFailed());
        $this->assertTrue($t2->isComplete() || $t2->isFailed());
    }
}

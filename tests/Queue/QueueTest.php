<?php

declare(strict_types=1);

namespace Nex\Tests\Queue;

use Nex\Database\Connection;
use Nex\Queue\DatabaseQueue;
use Nex\Queue\QueueWorker;
use PDO;
use PHPUnit\Framework\TestCase;

// ---- Test job classes ----

final class SimpleJob
{
    public static int $handleCount = 0;

    public function __construct(public readonly string $value = 'test') {}

    public function handle(): void
    {
        self::$handleCount++;
    }
}

final class FailingJob
{
    public function __construct(public readonly string $value = '') {}

    public function handle(): void
    {
        throw new \RuntimeException("Job always fails");
    }
}

// ---- Tests ----

final class QueueTest extends TestCase
{
    private DatabaseQueue $queue;
    private Connection $connection;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($pdo, 'sqlite');
        $this->queue = new DatabaseQueue($this->connection);
        $this->queue->createTables();
        SimpleJob::$handleCount = 0;
    }

    public function test_push_and_pop_a_job(): void
    {
        $job = new SimpleJob('hello');
        $id = $this->queue->push($job);

        $this->assertNotEmpty($id);

        $queued = $this->queue->pop();
        $this->assertNotNull($queued);
        $this->assertSame(SimpleJob::class, $queued->getJobClass());
        $this->assertSame(1, $queued->getAttempts());
    }

    public function test_pop_returns_null_when_queue_is_empty(): void
    {
        $result = $this->queue->pop();
        $this->assertNull($result);
    }

    public function test_acknowledge_removes_job(): void
    {
        $this->queue->push(new SimpleJob());
        $job = $this->queue->pop();
        $this->assertNotNull($job);

        $this->queue->acknowledge($job);

        // Should be empty now
        $this->assertNull($this->queue->pop());
    }

    public function test_release_makes_job_available_again(): void
    {
        $this->queue->push(new SimpleJob());
        $job = $this->queue->pop();
        $this->assertNotNull($job);

        $this->queue->release($job, 0);

        $again = $this->queue->pop();
        $this->assertNotNull($again);
        $this->assertSame($job->getId(), $again->getId());
        $this->assertSame(2, $again->getAttempts());
    }

    public function test_fail_moves_job_to_failed_store(): void
    {
        $this->queue->push(new SimpleJob());
        $job = $this->queue->pop();
        $this->assertNotNull($job);

        $this->queue->fail($job, 'Something went wrong');

        $this->assertNull($this->queue->pop());

        $failed = $this->queue->getFailedJobs();
        $this->assertCount(1, $failed);
        $this->assertSame($job->getId(), $failed[0]['id']);
    }

    public function test_delayed_job_is_not_immediately_available(): void
    {
        $this->queue->push(new SimpleJob(), ['delay' => 3600]);

        $result = $this->queue->pop();
        $this->assertNull($result);
    }

    public function test_job_payload_is_serialized_safely(): void
    {
        $job = new SimpleJob('world');
        $this->queue->push($job);

        $queued = $this->queue->pop();
        $this->assertNotNull($queued);
        $payload = $queued->getPayload();

        $this->assertSame(SimpleJob::class, $payload['job']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame('world', $payload['data']['value']);
    }

    public function test_closure_job_cannot_be_queued(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // ClosureJob has a closure property — should throw
        $bad = new class {
            public \Closure $fn;
            public function __construct() { $this->fn = fn() => null; }
            public function handle(): void {}
        };

        $this->queue->push($bad);
    }

    public function test_worker_processes_job_successfully(): void
    {
        $this->queue->push(new SimpleJob('run'));
        $worker = new QueueWorker($this->queue);
        $processed = $worker->runOnce();

        $this->assertTrue($processed);
        $this->assertSame(1, SimpleJob::$handleCount);
        $this->assertNull($this->queue->pop());
    }

    public function test_worker_moves_job_to_failed_after_max_attempts(): void
    {
        // maxAttempts = 1, so it fails immediately
        $this->queue->push(new FailingJob(), ['attempts' => 1]);
        $worker = new QueueWorker($this->queue, backoffSeconds: 0);
        $worker->runOnce();

        $this->assertNull($this->queue->pop());
        $failed = $this->queue->getFailedJobs();
        $this->assertCount(1, $failed);
    }

    public function test_worker_releases_job_for_retry_before_max_attempts(): void
    {
        $this->queue->push(new FailingJob(), ['attempts' => 3]);
        $worker = new QueueWorker($this->queue, backoffSeconds: 0);
        $worker->runOnce();

        // Job should be released (available again at delay=0)
        $retried = $this->queue->pop();
        $this->assertNotNull($retried);
        $this->assertSame(2, $retried->getAttempts());
    }

    public function test_retry_failed_job(): void
    {
        $this->queue->push(new SimpleJob());
        $job = $this->queue->pop();
        $this->assertNotNull($job);
        $this->queue->fail($job, 'error');

        $failed = $this->queue->getFailedJobs();
        $this->assertCount(1, $failed);

        $this->queue->retryFailedJob($failed[0]['id']);

        $this->assertEmpty($this->queue->getFailedJobs());
        $queued = $this->queue->pop();
        $this->assertNotNull($queued);
    }

    public function test_worker_returns_false_when_queue_is_empty(): void
    {
        $worker = new QueueWorker($this->queue);
        $this->assertFalse($worker->runOnce());
    }
}

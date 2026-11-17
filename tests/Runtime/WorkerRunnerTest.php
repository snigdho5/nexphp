<?php

declare(strict_types=1);

namespace Nex\Tests\Runtime;

use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Runtime\Contracts\WorkerConfiguration;
use Nex\Runtime\Contracts\WorkerLifecycleInterface;
use Nex\Runtime\Worker\WorkerRunner;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WorkerRunnerTest extends TestCase
{
    public function testWorkerLifecycleHooks(): void
    {
        $log = [];

        $lifecycle = new class($log) implements WorkerLifecycleInterface {
            public function __construct(private array &$log)
            {
            }
            public function onWorkerStart(): void
            {
                $this->log[] = 'worker_start';
            }
            public function onRequestStart(): void
            {
                $this->log[] = 'request_start';
            }
            public function onRequestEnd(): void
            {
                $this->log[] = 'request_end';
            }
            public function onWorkerStop(): void
            {
                $this->log[] = 'worker_stop';
            }
        };

        $app = Application::create();
        $app->get('/ping', fn() => ['status' => 'pong']);

        $config = new WorkerConfiguration(maxRequests: 2);
        $requestQueue = [
            new ServerRequest('GET', '/ping'),
            new ServerRequest('GET', '/ping'),
        ];

        $silentEmitter = new class implements \Nex\Contracts\Http\ResponseEmitterInterface {
            public function emit(\Psr\Http\Message\ResponseInterface $response): void
            {
                // Silent for test
            }
        };

        $runner = new WorkerRunner(
            config: $config,
            lifecycle: $lifecycle,
            emitter: $silentEmitter,
            requestProvider: function () use (&$requestQueue) {
                return array_shift($requestQueue);
            }
        );

        $runner->run($app);

        $this->assertSame(2, $runner->getProcessedRequests());
        $this->assertTrue($runner->shouldRecycle());
        $this->assertStringContainsString('Max requests limit reached', (string) $runner->getRecycleReason());

        $expected = [
            'worker_start',
            'request_start',
            'request_end',
            'request_start',
            'request_end',
            'worker_stop',
        ];
        $this->assertSame($expected, $log);
    }

    public function testMemoryRecyclingCheck(): void
    {
        // Low threshold to test memory check
        $config = new WorkerConfiguration(maxRequests: 1000, maxMemoryBytes: 1024);
        $runner = new WorkerRunner($config);

        $this->assertTrue($runner->shouldRecycle());
        $this->assertStringContainsString('Memory limit exceeded', (string) $runner->getRecycleReason());
    }
}

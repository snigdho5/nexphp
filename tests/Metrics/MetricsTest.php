<?php

declare(strict_types=1);

namespace Nex\Tests\Metrics;

use Nex\Metrics\MetricsCollector;
use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    private MetricsCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MetricsCollector();
    }

    public function test_counters(): void
    {
        $this->collector->incrementCounter('http_requests_total', 1, ['method' => 'GET', 'status' => '200']);
        $this->collector->incrementCounter('http_requests_total', 2, ['method' => 'GET', 'status' => '200']);
        $this->collector->incrementCounter('http_requests_total', 1, ['method' => 'POST', 'status' => '201']);

        $this->assertSame(3, $this->collector->getCounter('http_requests_total', ['method' => 'GET', 'status' => '200']));
        $this->assertSame(1, $this->collector->getCounter('http_requests_total', ['method' => 'POST', 'status' => '201']));
        $this->assertSame(0, $this->collector->getCounter('http_requests_total', ['method' => 'DELETE']));
    }

    public function test_gauges(): void
    {
        $this->collector->setGauge('worker_memory_bytes', 4194304, ['worker_id' => '1']);
        $this->assertSame(4194304, $this->collector->getGauge('worker_memory_bytes', ['worker_id' => '1']));

        $this->collector->setGauge('worker_memory_bytes', 5242880, ['worker_id' => '1']);
        $this->assertSame(5242880, $this->collector->getGauge('worker_memory_bytes', ['worker_id' => '1']));
    }

    public function test_histograms(): void
    {
        $this->collector->observeHistogram('http_request_duration_seconds', 0.025, ['handler' => 'users']);
        $this->collector->observeHistogram('http_request_duration_seconds', 0.045, ['handler' => 'users']);

        $samples = $this->collector->getHistogramSamples('http_request_duration_seconds', ['handler' => 'users']);
        $this->assertSame([0.025, 0.045], $samples);
    }

    public function test_prometheus_exposition_export(): void
    {
        $this->collector->incrementCounter('requests_total', 5, ['env' => 'prod']);
        $this->collector->setGauge('queue_size', 12, ['queue' => 'default']);
        $this->collector->observeHistogram('latency', 0.015, ['route' => 'ping']);

        $output = $this->collector->toPrometheus();

        $this->assertStringContainsString('# TYPE requests_total counter', $output);
        $this->assertStringContainsString('requests_total{env="prod"} 5', $output);
        $this->assertStringContainsString('# TYPE queue_size gauge', $output);
        $this->assertStringContainsString('queue_size{queue="default"} 12', $output);
        $this->assertStringContainsString('# TYPE latency histogram', $output);
        $this->assertStringContainsString('latency_count{route="ping"} 1', $output);
        $this->assertStringContainsString('latency_sum{route="ping"} 0.0150', $output);
    }
}

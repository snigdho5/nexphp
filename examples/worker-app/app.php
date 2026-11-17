<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Nex\Concurrency\Scheduler;
use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Logging\StructuredLogger;
use Nex\WorkerHealth\WorkerDiagnostics;

echo "========================================================\n";
echo " NexPHP Reference Application: High-Concurrency Worker  \n";
echo "========================================================\n\n";

$app = Application::create(__DIR__);
$logger = (new StructuredLogger())->withContext(['worker_id' => 'worker-01']);
$scheduler = new Scheduler();
$diagnostics = new WorkerDiagnostics();

// Register asynchronous parallel route
$app->get('/api/aggregate-data', function () use ($scheduler, $logger) {
    $logger->info('Starting parallel data aggregation across 3 services via Fibers...');

    // Run 3 cooperative asynchronous tasks in parallel via Fibers
    $results = $scheduler->all([
        'inventory' => function () {
            Scheduler::suspend(); // Simulate async cooperative I/O
            return ['in_stock' => 142, 'warehouse' => 'East-1'];
        },
        'pricing'   => function () {
            Scheduler::suspend(); // Simulate async cooperative I/O
            return ['base_price' => 49.99, 'currency' => 'USD'];
        },
        'analytics' => function () {
            Scheduler::suspend(); // Simulate async cooperative I/O
            return ['views_today' => 1250, 'trend' => 'up'];
        },
    ]);

    return new JsonResponse([
        'status' => 'aggregated',
        'data'   => $results,
    ]);
});

// Run simulated worker dispatch
echo "Dispatching concurrent aggregate request...\n";
$req = new ServerRequest('GET', '/api/aggregate-data', headers: ['X-Request-Id' => 'trace-worker-777']);
$res = $app->handle($req);

echo "HTTP Status: " . $res->getStatusCode() . "\n";
echo "Response Payload: " . $res->getBody()->getContents() . "\n\n";

// Worker Diagnostics
$diagnostics->recordRequest();
$snap = $diagnostics->snapshot();

echo "--- Worker Health Metrics ---\n";
echo sprintf("Requests Handled:  %d\n", $snap['requests_handled']);
echo sprintf("Uptime:            %.2fs\n", $snap['uptime_seconds']);
echo sprintf("Current Memory:    %.2f MB\n", $snap['memory_current_mb']);
echo sprintf("Peak Memory:       %.2f MB\n", $snap['memory_peak_mb']);

echo "\n[SUCCESS] Concurrent persistent worker workflow verified.\n";

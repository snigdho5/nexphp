<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nex\Concurrency\Scheduler;
use Nex\Foundation\Application;
use Nex\Http\ServerRequest;

echo "========================================================\n";
echo "           NexPHP Phase 3 Runtime Benchmark            \n";
echo "========================================================\n\n";

// 1. Cold boot vs warm boot
$bootStart = microtime(true);
$app = Application::create();
$app->get('/bench', fn() => ['status' => 'ok', 'time' => microtime(true)]);
$app->boot();
$bootTime = (microtime(true) - $bootStart) * 1000;

echo sprintf("Application Boot Time: %.2f ms\n", $bootTime);

// 2. Request Dispatch Throughput (in-memory)
$iterations = 5000;
$req = new ServerRequest('GET', '/bench');
$startDispatch = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $response = $app->handle($req);
}

$totalDispatchTime = microtime(true) - $startDispatch;
$reqPerSec = $iterations / $totalDispatchTime;

echo sprintf("Throughput: %d requests in %.4f s (%.0f req/sec)\n", $iterations, $totalDispatchTime, $reqPerSec);

// 3. Fiber Concurrency Benchmark
$scheduler = new Scheduler();
$fiberTasks = 200;
$startFibers = microtime(true);

$tasks = [];
for ($i = 0; $i < $fiberTasks; $i++) {
    $tasks[] = function () {
        Scheduler::suspend();
        return true;
    };
}

$results = $scheduler->all($tasks);
$fiberTime = (microtime(true) - $startFibers) * 1000;

echo sprintf("Fiber Concurrency: %d interleaved tasks in %.2f ms (%.0f tasks/sec)\n", $fiberTasks, $fiberTime, ($fiberTasks / ($fiberTime / 1000)));

// 4. Memory footprint
$memCurrent = round(memory_get_usage(true) / (1024 * 1024), 2);
$memPeak = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

echo sprintf("Memory Usage: Current: %.2f MB | Peak: %.2f MB\n\n", $memCurrent, $memPeak);
echo "Benchmark completed successfully.\n";

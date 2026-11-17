<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\Response;
use Nex\Http\ServerRequest;
use Nex\Testing\TestResponse;

echo "========================================================\n";
echo " NexPHP Worker Soak & Reliability Test (10,000 Requests)\n";
echo "========================================================\n\n";

$app = Application::create(__DIR__ . '/..');

// Register test routes
$app->get('/api/ping', function () {
    return new JsonResponse(['status' => 'pong', 'timestamp' => microtime(true)]);
});

$app->post('/api/echo', function (ServerRequest $request) {
    return new JsonResponse([
        'received' => $request->getParsedBody(),
    ]);
});

$initialMemory = memory_get_usage(true);
$iterations = 10_000;
$startTime = microtime(true);

echo sprintf("Initial Memory: %.2f MB\n", $initialMemory / 1024 / 1024);
echo "Executing {$iterations} persistent worker requests...\n";

for ($i = 1; $i <= $iterations; $i++) {
    $request = new ServerRequest(
        method: $i % 2 === 0 ? 'POST' : 'GET',
        uri: $i % 2 === 0 ? '/api/echo' : '/api/ping',
        headers: ['Content-Type' => 'application/json'],
    );
    if ($i % 2 === 0) {
        $request = $request->withParsedBody(['iteration' => $i]);
    }

    $response = $app->handle($request);

    if ($response->getStatusCode() !== 200) {
        echo "Error on iteration {$i}: HTTP " . $response->getStatusCode() . "\n";
        exit(1);
    }

    if ($i % 2500 === 0) {
        $currentMem = memory_get_usage(true);
        echo sprintf("  [%5d / %5d] Current Memory: %.2f MB\n", $i, $iterations, $currentMem / 1024 / 1024);
    }
}

$endTime = microtime(true);
$finalMemory = memory_get_usage(true);
$duration = $endTime - $startTime;
$rps = round($iterations / $duration, 2);
$memoryGrowth = ($finalMemory - $initialMemory) / 1024 / 1024;

echo "\n--- Soak Test Results ---\n";
echo sprintf("Total Requests:  %s\n", number_format($iterations));
echo sprintf("Total Duration:  %.3f seconds\n", $duration);
echo sprintf("Throughput:      %s req/sec\n", number_format($rps, 2));
echo sprintf("Final Memory:    %.2f MB\n", $finalMemory / 1024 / 1024);
echo sprintf("Memory Growth:   %.2f MB\n", $memoryGrowth);

if ($memoryGrowth > 2.0) {
    echo "\n[WARNING] Potential memory leak detected: Memory grew by > 2MB\n";
    exit(1);
} else {
    echo "\n[SUCCESS] Memory stable. Zero leaks detected.\n";
}

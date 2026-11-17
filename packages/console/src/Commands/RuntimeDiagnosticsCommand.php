<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\WorkerHealth\WorkerDiagnostics;

class RuntimeDiagnosticsCommand extends Command
{
    protected string $name = 'runtime:diagnostics';
    protected string $description = 'Display runtime capabilities and worker performance diagnostics';

    public function execute(array $args = []): int
    {
        $this->output->info('NexPHP Runtime & Concurrency Diagnostics');

        $headers = ['Capability / Metric', 'Status / Value'];
        $rows = [
            ['PHP Version', PHP_VERSION],
            ['Native Fibers Supported', class_exists(\Fiber::class) ? 'YES' : 'NO'],
            ['Persistent Worker Ready', 'YES (FrankenPHP & WorkerRunner)'],
            ['cURL Multi Concurrency', function_exists('curl_multi_init') ? 'AVAILABLE' : 'UNAVAILABLE'],
            ['PCNTL Signals', function_exists('pcntl_signal') ? 'ENABLED' : 'DISABLED'],
            ['Current Memory (MB)', round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB'],
            ['Peak Memory (MB)', round(memory_get_peak_usage(true) / (1024 * 1024), 2) . ' MB'],
        ];

        $this->output->table($headers, $rows);

        return 0;
    }
}

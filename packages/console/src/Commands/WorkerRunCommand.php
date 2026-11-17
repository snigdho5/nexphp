<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Runtime\Contracts\WorkerConfiguration;
use Nex\Runtime\FrankenPhp\FrankenPhpRunner;

class WorkerRunCommand extends Command
{
    protected string $name = 'worker:run';
    protected string $description = 'Run the NexPHP application in persistent worker mode';

    public function execute(array $args = []): int
    {
        $this->app->boot();

        $maxRequests = isset($args['max-requests']) ? (int) $args['max-requests'] : 10000;
        $maxMemoryMb = isset($args['max-memory']) ? (int) $args['max-memory'] : 128;

        $config = new WorkerConfiguration(
            maxRequests: $maxRequests,
            maxMemoryBytes: $maxMemoryMb * 1024 * 1024
        );

        $this->output->info("Starting NexPHP persistent worker (Max requests: $maxRequests, Max memory: {$maxMemoryMb}MB)");

        $runner = new FrankenPhpRunner($config);
        $runner->run($this->app);

        return 0;
    }
}

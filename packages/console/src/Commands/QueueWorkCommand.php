<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Queue\DatabaseQueue;
use Nex\Queue\QueueWorker;

class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Process jobs from the queue';

    public function execute(array $args = []): int
    {
        $queue = $args[0] ?? 'default';
        $this->output->info("Starting queue worker on queue [{$queue}]...");
        $this->output->info("Press Ctrl+C to stop.");

        $db = $this->app->make(\Nex\Database\Contracts\ConnectionInterface::class);
        $dbQueue = new DatabaseQueue($db);
        $dbQueue->createTables();

        $worker = new QueueWorker($dbQueue);

        // Handle SIGTERM/SIGINT gracefully
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $worker->stop());
            pcntl_signal(SIGINT, fn () => $worker->stop());
        }

        $worker->run($queue);

        $this->output->success("Queue worker stopped.");
        return 0;
    }
}

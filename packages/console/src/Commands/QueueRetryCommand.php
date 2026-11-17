<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Queue\DatabaseQueue;

class QueueRetryCommand extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry a failed job by ID';

    public function execute(array $args = []): int
    {
        $id = $args[0] ?? null;
        if ($id === null) {
            $this->output->error('Job ID required. Usage: nex queue:retry <job-id>');
            return 1;
        }

        $db = $this->app->make(\Nex\Database\Contracts\ConnectionInterface::class);
        $dbQueue = new DatabaseQueue($db);
        $dbQueue->createTables();
        $dbQueue->retryFailedJob($id);

        $this->output->success("Job [{$id}] queued for retry.");
        return 0;
    }
}

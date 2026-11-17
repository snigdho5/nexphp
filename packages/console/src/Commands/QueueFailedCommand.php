<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Queue\DatabaseQueue;

class QueueFailedCommand extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all failed jobs';

    public function execute(array $args = []): int
    {
        $db = $this->app->make(\Nex\Database\Contracts\ConnectionInterface::class);
        $dbQueue = new DatabaseQueue($db);
        $dbQueue->createTables();

        $jobs = $dbQueue->getFailedJobs();

        if (empty($jobs)) {
            $this->output->info("No failed jobs.");
            return 0;
        }

        $this->output->table(
            ['ID', 'Queue', 'Job Class', 'Failed At'],
            array_map(fn ($j) => [
                substr((string) $j['id'], 0, 16) . '...',
                (string) $j['queue'],
                json_decode((string) $j['payload'], true)['job'] ?? '?',
                date('Y-m-d H:i:s', (int) $j['failed_at']),
            ], $jobs),
        );

        return 0;
    }
}

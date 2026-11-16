<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Database\Contracts\DatabaseManagerInterface;
use Nex\Database\Migrations\Migrator;

class MigrateRollbackCommand extends Command
{
    protected string $name = 'migrate:rollback';
    protected string $description = 'Rollback the last database migration batch';

    public function execute(array $args = []): int
    {
        $this->app->boot();

        /** @var DatabaseManagerInterface $db */
        $db = $this->app->make(DatabaseManagerInterface::class);
        $conn = $db->connection();

        $migrator = new Migrator($conn);
        $migrationDir = $this->app->basePath('database/migrations');

        $rolledBack = $migrator->rollback($migrationDir);

        if (empty($rolledBack)) {
            $this->output->info('Nothing to rollback.');
            return 0;
        }

        foreach ($rolledBack as $name) {
            $this->output->info("Rolled back: $name");
        }

        return 0;
    }
}

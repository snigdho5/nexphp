<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Database\Contracts\DatabaseManagerInterface;
use Nex\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run the database migrations';

    public function execute(array $args = []): int
    {
        $this->app->boot();

        /** @var DatabaseManagerInterface $db */
        $db = $this->app->make(DatabaseManagerInterface::class);
        $conn = $db->connection();

        $migrator = new Migrator($conn);
        $migrationDir = $this->app->basePath('database/migrations');

        $migrated = $migrator->run($migrationDir);

        if (empty($migrated)) {
            $this->output->info('Nothing to migrate.');
            return 0;
        }

        foreach ($migrated as $name) {
            $this->output->success("Migrated: $name");
        }

        return 0;
    }
}

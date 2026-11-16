<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the local development server';

    public function execute(array $args = []): int
    {
        $host = $args['host'] ?? '127.0.0.1';
        $port = $args['port'] ?? '8000';
        $publicPath = $this->app->basePath('public');

        if (!is_dir($publicPath)) {
            $publicPath = $this->app->basePath();
        }

        $this->output->info("Starting NexPHP development server at http://$host:$port");
        $this->output->writeln("Press Ctrl+C to stop.");

        passthru(sprintf('php -S %s:%s -t %s', escapeshellarg($host), escapeshellarg((string) $port), escapeshellarg($publicPath)));

        return 0;
    }
}

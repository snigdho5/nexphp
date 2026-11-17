<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class TestCommand extends Command
{
    protected string $name = 'test';
    protected string $description = 'Run the application test suite with PHPUnit';

    public function execute(array $args = []): int
    {
        $phpunitBin = $this->app->basePath('vendor/bin/phpunit');
        if (!file_exists($phpunitBin)) {
            $phpunitBin = 'phpunit';
        }

        $cmd = escapeshellcmd($phpunitBin) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $this->output->info("Running tests: {$cmd}");

        passthru($cmd, $exitCode);
        return $exitCode;
    }
}

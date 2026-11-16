<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class AboutCommand extends Command
{
    protected string $name = 'about';
    protected string $description = 'Display information about the current NexPHP application';

    public function execute(array $args = []): int
    {
        $this->output->info('NexPHP Application Information');

        $headers = ['Component', 'Details'];
        $rows = [
            ['NexPHP Version', $this->app->version()],
            ['PHP Version', PHP_VERSION],
            ['Environment', (string) $this->app->config('app.env', 'production')],
            ['Debug Mode', $this->app->config('app.debug', false) ? 'ENABLED' : 'DISABLED'],
            ['Operating System', PHP_OS_FAMILY . ' (' . php_uname('m') . ')'],
            ['OPcache Active', function_exists('opcache_get_status') && opcache_get_status() ? 'YES' : 'NO'],
        ];

        $this->output->table($headers, $rows);

        return 0;
    }
}

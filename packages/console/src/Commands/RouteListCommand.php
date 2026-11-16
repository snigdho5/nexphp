<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Closure;
use Nex\Console\Command;

class RouteListCommand extends Command
{
    protected string $name = 'route:list';
    protected string $description = 'List all registered routes in the application';

    public function execute(array $args = []): int
    {
        $this->app->boot();

        $routes = $this->app->router()->getRoutes();

        if (empty($routes)) {
            $this->output->info('No routes registered.');
            return 0;
        }

        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware'];
        $rows = [];

        foreach ($routes as $route) {
            $methods = implode('|', $route->getMethods());
            $uri = $route->getPath();
            $name = $route->getName() ?? '-';
            $handler = $route->getHandler();

            $action = match (true) {
                $handler instanceof Closure => 'Closure',
                is_array($handler) => is_object($handler[0]) ? get_class($handler[0]) . '@' . $handler[1] : $handler[0] . '@' . $handler[1],
                is_string($handler) => $handler,
                default => 'Unknown',
            };

            $middlewareList = implode(', ', array_map(fn($m) => is_object($m) ? get_class($m) : (string) $m, $route->getMiddleware()));
            if ($middlewareList === '') {
                $middlewareList = '-';
            }

            $rows[] = [$methods, $uri, $name, $action, $middlewareList];
        }

        $this->output->table($headers, $rows);

        return 0;
    }
}

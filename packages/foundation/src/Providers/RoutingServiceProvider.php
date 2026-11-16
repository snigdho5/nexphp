<?php

declare(strict_types=1);

namespace Nex\Foundation\Providers;

use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Foundation\ServiceProviderInterface;
use Nex\Contracts\Routing\RouterInterface;
use Nex\Routing\Router;

class RoutingServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(RouterInterface::class, function (ContainerInterface $c) {
            return new Router($c);
        });

        $container->alias(RouterInterface::class, 'router');
    }

    public function boot(ContainerInterface $container): void
    {
    }
}

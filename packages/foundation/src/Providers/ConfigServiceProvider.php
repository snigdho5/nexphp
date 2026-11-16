<?php

declare(strict_types=1);

namespace Nex\Foundation\Providers;

use Nex\Config\Repository;
use Nex\Contracts\Config\RepositoryInterface;
use Nex\Contracts\Container\ContainerInterface;
use Nex\Contracts\Foundation\ServiceProviderInterface;

class ConfigServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(RepositoryInterface::class, function () {
            return new Repository();
        });

        $container->alias(RepositoryInterface::class, 'config');
    }

    public function boot(ContainerInterface $container): void
    {
    }
}

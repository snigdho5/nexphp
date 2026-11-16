<?php

declare(strict_types=1);

namespace Nex\Contracts\Foundation;

use Nex\Contracts\Container\ContainerInterface;

interface ServiceProviderInterface
{
    /**
     * Register any application services in the container.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap any application services.
     */
    public function boot(ContainerInterface $container): void;
}

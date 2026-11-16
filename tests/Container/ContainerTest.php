<?php

declare(strict_types=1);

namespace Nex\Tests\Container;

use Nex\Container\Container;
use Nex\Contracts\Container\CircularDependencyException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBasicBindAndResolve(): void
    {
        $this->container->bind('greeting', fn() => 'hello world');

        $this->assertTrue($this->container->has('greeting'));
        $this->assertSame('hello world', $this->container->get('greeting'));
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('std', fn() => new stdClass());

        $obj1 = $this->container->make('std');
        $obj2 = $this->container->make('std');

        $this->assertSame($obj1, $obj2);
    }

    public function testScopedResolutionAndForgetScoped(): void
    {
        $this->container->scoped('request_state', fn() => new stdClass());

        $state1 = $this->container->make('request_state');
        $state2 = $this->container->make('request_state');

        $this->assertSame($state1, $state2);

        // Reset request scope
        $this->container->forgetScoped();

        $state3 = $this->container->make('request_state');
        $this->assertNotSame($state1, $state3);
    }

    public function testAutowiringDependencies(): void
    {
        $service = $this->container->make(OrderService::class);

        $this->assertInstanceOf(OrderService::class, $service);
        $this->assertInstanceOf(DatabaseConnection::class, $service->db);
    }

    public function testCircularDependencyDetection(): void
    {
        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->make(NodeA::class);
    }

    public function testCallInvokesWithInjectedParameters(): void
    {
        $result = $this->container->call(function (OrderService $service, string $status = 'active') {
            return $status . ':' . get_class($service->db);
        });

        $this->assertSame('active:' . DatabaseConnection::class, $result);
    }
}

class DatabaseConnection
{
}

class OrderService
{
    public function __construct(public DatabaseConnection $db)
    {
    }
}

class NodeA
{
    public function __construct(public NodeB $b)
    {
    }
}

class NodeB
{
    public function __construct(public NodeA $a)
    {
    }
}

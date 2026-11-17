<?php

declare(strict_types=1);

namespace Nex\Tests\Health;

use Nex\Database\Connection;
use Nex\Filesystem\LocalFilesystem;
use Nex\Health\DatabaseHealthCheck;
use Nex\Health\HealthCheckInterface;
use Nex\Health\HealthCheckResult;
use Nex\Health\HealthManager;
use Nex\Health\StorageHealthCheck;
use PDO;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function test_liveness_probe(): void
    {
        $manager = new HealthManager();
        $liveness = $manager->liveness();

        $this->assertSame('ok', $liveness['status']);
        $this->assertArrayHasKey('timestamp', $liveness);
        $this->assertArrayHasKey('memory_mb', $liveness);
    }

    public function test_database_health_check_success(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = new Connection($pdo, 'sqlite');
        $check = new DatabaseHealthCheck($connection);

        $result = $check->check();
        $this->assertTrue($result->isHealthy());
        $this->assertSame('database', $result->name);
        $this->assertSame('ok', $result->status);
        $this->assertArrayHasKey('latency_ms', $result->meta);
    }

    public function test_storage_health_check_success(): void
    {
        $tmpDir = sys_get_temp_dir() . '/nex_health_fs_' . uniqid();
        $fs = new LocalFilesystem($tmpDir);
        $check = new StorageHealthCheck($fs);

        $result = $check->check();
        $this->assertTrue($result->isHealthy());
        $this->assertSame('storage', $result->name);

        $fs->deleteDirectory('');
        @rmdir($tmpDir);
    }

    public function test_health_manager_readiness_aggregation(): void
    {
        $manager = new HealthManager();

        $okCheck = new class implements HealthCheckInterface {
            public function name(): string { return 'service_a'; }
            public function check(): HealthCheckResult { return HealthCheckResult::ok('service_a'); }
        };

        $warnCheck = new class implements HealthCheckInterface {
            public function name(): string { return 'service_b'; }
            public function check(): HealthCheckResult { return HealthCheckResult::warning('service_b', 'High latency'); }
        };

        $manager->register($okCheck)->register($warnCheck);

        $readiness = $manager->readiness();
        $this->assertSame('degraded', $readiness['status']);
        $this->assertTrue($manager->isReady());

        // Add critical failure
        $critCheck = new class implements HealthCheckInterface {
            public function name(): string { return 'service_c'; }
            public function check(): HealthCheckResult { return HealthCheckResult::critical('service_c', 'Outage'); }
        };
        $manager->register($critCheck);

        $readiness2 = $manager->readiness();
        $this->assertSame('unhealthy', $readiness2['status']);
        $this->assertFalse($manager->isReady());
    }
}

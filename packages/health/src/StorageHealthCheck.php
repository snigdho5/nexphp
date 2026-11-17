<?php

declare(strict_types=1);

namespace Nex\Health;

use Nex\Filesystem\Contracts\FilesystemInterface;
use Throwable;

final class StorageHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly string $name = 'storage',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        $testFile = '.health_check_' . bin2hex(random_bytes(8));

        try {
            $this->filesystem->write($testFile, 'probe');
            $read = $this->filesystem->read($testFile);
            $this->filesystem->delete($testFile);

            if ($read !== 'probe') {
                return HealthCheckResult::critical($this->name, 'Storage write/read verification mismatch');
            }

            return HealthCheckResult::ok($this->name, 'Storage is writable and readable');
        } catch (Throwable $e) {
            return HealthCheckResult::critical($this->name, 'Storage check failed: unable to write or read');
        }
    }
}

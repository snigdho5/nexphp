<?php

declare(strict_types=1);

namespace Nex\Health;

final class HealthCheckResult
{
    /**
     * @param 'ok'|'warning'|'critical' $status
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $message = '',
        public readonly array $meta = [],
    ) {}

    public static function ok(string $name, string $message = 'OK', array $meta = []): static
    {
        return new static($name, 'ok', $message, $meta);
    }

    public static function warning(string $name, string $message, array $meta = []): static
    {
        return new static($name, 'warning', $message, $meta);
    }

    public static function critical(string $name, string $message, array $meta = []): static
    {
        return new static($name, 'critical', $message, $meta);
    }

    public function isHealthy(): bool
    {
        return $this->status === 'ok';
    }

    public function toArray(): array
    {
        $data = [
            'status'  => $this->status,
            'message' => $this->message,
        ];

        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace Nex\Logging;

/**
 * Redacts sensitive fields (passwords, tokens, secrets, cookies, cards) from log contexts.
 */
final class Redactor
{
    private const array DEFAULT_SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'set-cookie',
        'credit_card',
        'cvv',
        'private_key',
    ];

    /**
     * @param list<string> $sensitiveKeys
     */
    public function __construct(
        private readonly array $sensitiveKeys = self::DEFAULT_SENSITIVE_KEYS,
        private readonly string $mask = '[REDACTED]',
    ) {}

    /**
     * Recursively redact sensitive values from an array or scalar.
     */
    public function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = $this->mask;
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redact($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach ($this->sensitiveKeys as $sensitive) {
            $normalizedSensitive = strtolower(str_replace(['-', '_'], '', $sensitive));
            if (str_contains($normalized, $normalizedSensitive)) {
                return true;
            }
        }

        return false;
    }
}

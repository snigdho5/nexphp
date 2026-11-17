<?php

declare(strict_types=1);

namespace Nex\Security;

/**
 * In-memory sliding-window rate limiter.
 *
 * Tracks request timestamps per key and prunes old hits outside the decay window.
 */
final class RateLimiter
{
    /**
     * @var array<string, list<int>> [key => list of timestamp integers]
     */
    private array $hits = [];

    /**
     * Increment the counter for a given key for a decay duration.
     * Returns the new total hit count within the decay window.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $now = time();
        $this->prune($key, $now - $decaySeconds);
        $this->hits[$key][] = $now;

        return count($this->hits[$key]);
    }

    /**
     * Determine if the key has exceeded max attempts within decay window.
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $now = time();
        $this->prune($key, $now - $decaySeconds);

        return ($this->attempts($key)) >= $maxAttempts;
    }

    /**
     * Get the number of attempts for a given key.
     */
    public function attempts(string $key): int
    {
        return count($this->hits[$key] ?? []);
    }

    /**
     * Get the remaining attempts for a given key.
     */
    public function remaining(string $key, int $maxAttempts, int $decaySeconds = 60): int
    {
        $now = time();
        $this->prune($key, $now - $decaySeconds);

        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Seconds until the earliest hit expires.
     */
    public function availableIn(string $key, int $decaySeconds = 60): int
    {
        if (empty($this->hits[$key])) {
            return 0;
        }

        $oldest = min($this->hits[$key]);
        $expiresAt = $oldest + $decaySeconds;

        return max(0, $expiresAt - time());
    }

    /**
     * Reset the attempts for a given key.
     */
    public function reset(string $key): void
    {
        unset($this->hits[$key]);
    }

    /**
     * Clear all recorded rate limits.
     */
    public function clear(): void
    {
        $this->hits = [];
    }

    private function prune(string $key, int $threshold): void
    {
        if (!isset($this->hits[$key])) {
            return;
        }

        $this->hits[$key] = array_values(array_filter(
            $this->hits[$key],
            fn (int $ts) => $ts > $threshold,
        ));

        if (empty($this->hits[$key])) {
            unset($this->hits[$key]);
        }
    }
}

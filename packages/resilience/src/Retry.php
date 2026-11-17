<?php

declare(strict_types=1);

namespace Nex\Resilience;

use Throwable;

/**
 * Bounded retries with exponential backoff and random jitter.
 *
 * Prevents thundering herds by adding randomized jitter to retry intervals.
 */
final class Retry
{
    /**
     * Execute an action with bounded retries, exponential backoff, and jitter.
     *
     * @template T
     * @param callable():T $action The callable to execute
     * @param int $maxAttempts Maximum number of attempts (default: 3)
     * @param int $initialDelayMs Initial sleep delay in milliseconds (default: 50ms)
     * @param float $multiplier Multiplier for exponential backoff (default: 2.0)
     * @param float $jitterFactor Random jitter percentage (0.0 - 1.0, default: 0.2)
     * @param ?callable(Throwable):bool $when Optional predicate returning true if error should be retried
     * @return T
     * @throws Throwable The last encountered exception if all attempts fail
     */
    public static function run(
        callable $action,
        int $maxAttempts = 3,
        int $initialDelayMs = 50,
        float $multiplier = 2.0,
        float $jitterFactor = 0.2,
        ?callable $when = null,
    ): mixed {
        $attempt = 1;
        $delayMs = $initialDelayMs;

        while (true) {
            try {
                return $action();
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                if ($when !== null && !$when($e)) {
                    throw $e;
                }

                // Add random jitter: delay +/- (jitterFactor * delay)
                $jitterRange = (int) ($delayMs * $jitterFactor);
                $actualDelayMs = $delayMs + random_int(-$jitterRange, $jitterRange);
                $actualDelayMs = max(1, $actualDelayMs);

                usleep($actualDelayMs * 1000);

                $delayMs = (int) ($delayMs * $multiplier);
                $attempt++;
            }
        }
    }
}

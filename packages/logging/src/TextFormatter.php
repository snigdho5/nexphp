<?php

declare(strict_types=1);

namespace Nex\Logging;

use DateTimeImmutable;
use Throwable;

/**
 * Formats log entries as human-readable strings for development.
 */
final class TextFormatter implements FormatterInterface
{
    public function format(string $level, string $message, array $context, DateTimeImmutable $timestamp): string
    {
        $time = $timestamp->format('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        $color = match ($levelUpper) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => "\033[31m",
            'WARNING'                                  => "\033[33m",
            'NOTICE', 'INFO'                           => "\033[32m",
            default                                    => "\033[34m",
        };
        $reset = "\033[0m";

        $line = sprintf("[%s] %s%s%s: %s", $time, $color, $levelUpper, $reset, $message);

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $e = $context['exception'];
            $line .= sprintf("\n  Exception: %s: %s in %s:%d", $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
            unset($context['exception']);
        }

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $line . "\n";
    }
}

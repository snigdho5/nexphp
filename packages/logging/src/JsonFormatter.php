<?php

declare(strict_types=1);

namespace Nex\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Formats log entries as single-line JSON records for production log aggregators (ELK, Datadog, CloudWatch).
 */
final class JsonFormatter implements FormatterInterface
{
    public function format(string $level, string $message, array $context, DateTimeImmutable $timestamp): string
    {
        $record = [
            'timestamp' => $timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
            'level'     => strtolower($level),
            'message'   => $message,
        ];

        // Format exceptions nicely
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $e = $context['exception'];
            $context['exception'] = [
                'class'   => $e::class,
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
        }

        if (!empty($context)) {
            $record['context'] = $context;
        }

        return json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

<?php

declare(strict_types=1);

namespace Nex\Logging;

use DateTimeImmutable;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Production-ready structured logger implementing PSR-3 LoggerInterface.
 *
 * Features:
 * - Automatic sensitive data redaction
 * - Configurable output formatters (JsonFormatter, TextFormatter)
 * - Persistent global context (e.g. request_id, worker_id)
 * - PSR-3 message placeholder interpolation
 */
final class StructuredLogger extends AbstractLogger
{
    private const array VALID_LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];

    /**
     * @var array<string, mixed>
     */
    private array $globalContext = [];

    /**
     * @var list<string> in-memory log records (useful for testing)
     */
    private array $records = [];

    /**
     * @param resource|string|null $stream File path, open stream resource, or null for in-memory only
     */
    public function __construct(
        private mixed $stream = null,
        private readonly FormatterInterface $formatter = new JsonFormatter(),
        private readonly Redactor $redactor = new Redactor(),
        private readonly string $minLevel = LogLevel::DEBUG,
    ) {}

    /**
     * Set context values included with every log message (e.g. request_id, worker_id).
     */
    public function withContext(array $context): static
    {
        $clone = clone $this;
        $clone->globalContext = array_merge($this->globalContext, $context);
        return $clone;
    }

    public function setGlobalContext(string $key, mixed $value): void
    {
        $this->globalContext[$key] = $value;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelStr = (string) $level;

        if (!isset(self::VALID_LEVELS[$levelStr])) {
            throw new InvalidArgumentException("Invalid log level: [{$levelStr}]");
        }

        if (self::VALID_LEVELS[$levelStr] > self::VALID_LEVELS[$this->minLevel]) {
            return;
        }

        // Merge global context with record context
        $mergedContext = array_merge($this->globalContext, $context);

        // Interpolate PSR-3 placeholders: {foo} -> context['foo']
        $interpolatedMessage = $this->interpolate((string) $message, $mergedContext);

        // Redact sensitive data from context
        $redactedContext = $this->redactor->redact($mergedContext);

        $now = new DateTimeImmutable('now');
        $formatted = $this->formatter->format($levelStr, $interpolatedMessage, $redactedContext, $now);

        $this->records[] = $formatted;

        $this->write($formatted);
    }

    /**
     * @return list<string>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function clearRecords(): void
    {
        $this->records = [];
    }

    private function write(string $formatted): void
    {
        if ($this->stream === null) {
            return;
        }

        if (is_resource($this->stream)) {
            fwrite($this->stream, $formatted);
            return;
        }

        if (is_string($this->stream)) {
            file_put_contents($this->stream, $formatted, FILE_APPEND | LOCK_EX);
        }
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }
}

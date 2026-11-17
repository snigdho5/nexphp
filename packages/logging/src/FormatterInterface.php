<?php

declare(strict_types=1);

namespace Nex\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

interface FormatterInterface
{
    /**
     * Format a log record into a string.
     *
     * @param array<string, mixed> $context
     */
    public function format(string $level, string $message, array $context, DateTimeImmutable $timestamp): string;
}

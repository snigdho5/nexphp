<?php

declare(strict_types=1);

namespace Nex\Validation\Contracts;

interface ValidationResultInterface
{
    public function passes(): bool;

    public function fails(): bool;

    /**
     * Get only the validated fields.
     *
     * @return array<string, mixed>
     */
    public function validated(): array;

    /**
     * Get all validation error messages grouped by field.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array;

    public function firstError(?string $key = null): ?string;
}

<?php

declare(strict_types=1);

namespace Nex\Validation;

use Nex\Validation\Contracts\ValidationResultInterface;

class ValidationResult implements ValidationResultInterface
{
    /**
     * @param array<string, mixed> $validated
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private array $validated,
        private array $errors
    ) {
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(?string $key = null): ?string
    {
        if ($key !== null) {
            return $this->errors[$key][0] ?? null;
        }

        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }
}

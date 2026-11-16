<?php

declare(strict_types=1);

namespace Nex\Validation\Exceptions;

use Exception;

class ValidationException extends Exception
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private array $errors,
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

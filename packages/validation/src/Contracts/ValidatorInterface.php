<?php

declare(strict_types=1);

namespace Nex\Validation\Contracts;

interface ValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, list<string>|string> $rules
     */
    public function validate(array $data, array $rules): ValidationResultInterface;
}

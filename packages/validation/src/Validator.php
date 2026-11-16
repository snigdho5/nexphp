<?php

declare(strict_types=1);

namespace Nex\Validation;

use Nex\Validation\Contracts\ValidationResultInterface;
use Nex\Validation\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface
{
    public function validate(array $data, array $rules): ValidationResultInterface
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            $isNullable = in_array('nullable', $ruleList, true);
            $isRequired = in_array('required', $ruleList, true);

            // Handle missing or null values
            if ($value === null || $value === '') {
                if ($isRequired) {
                    $errors[$field][] = "The $field field is required.";
                    continue;
                }
                if ($isNullable) {
                    $validated[$field] = null;
                    continue;
                }
                // Optional field not provided
                continue;
            }

            $fieldPassed = true;

            foreach ($ruleList as $rule) {
                if ($rule === 'nullable' || $rule === 'required') {
                    continue;
                }

                [$ruleName, $param] = str_contains($rule, ':') ? explode(':', $rule, 2) : [$rule, null];

                $error = $this->validateRule($field, $value, $ruleName, $param, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    $fieldPassed = false;
                }
            }

            if ($fieldPassed) {
                $validated[$field] = $value;
            }
        }

        return new ValidationResult($validated, $errors);
    }

    private function validateRule(string $field, mixed $value, string $rule, ?string $param, array $allData): ?string
    {
        return match ($rule) {
            'string' => !is_string($value) ? "The $field field must be a string." : null,
            'integer' => (!is_int($value) && !ctype_digit((string) $value)) ? "The $field field must be an integer." : null,
            'numeric' => !is_numeric($value) ? "The $field field must be a number." : null,
            'boolean' => !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true) ? "The $field field must be a boolean." : null,
            'email' => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "The $field field must be a valid email address." : null,
            'url' => !filter_var($value, FILTER_VALIDATE_URL) ? "The $field field must be a valid URL." : null,
            'min' => $this->validateMin($field, $value, (int) $param),
            'max' => $this->validateMax($field, $value, (int) $param),
            'in' => $this->validateIn($field, $value, explode(',', (string) $param)),
            'confirmed' => ($value !== ($allData[$field . '_confirmation'] ?? null)) ? "The $field confirmation does not match." : null,
            'same' => ($value !== ($allData[$param] ?? null)) ? "The $field and $param must match." : null,
            'regex' => !preg_match((string) $param, (string) $value) ? "The $field format is invalid." : null,
            default => null,
        };
    }

    private function validateMin(string $field, mixed $value, int $min): ?string
    {
        if (is_numeric($value) && $value < $min) {
            return "The $field field must be at least $min.";
        }
        if (is_string($value) && mb_strlen($value) < $min) {
            return "The $field field must be at least $min characters.";
        }
        if (is_array($value) && count($value) < $min) {
            return "The $field field must have at least $min items.";
        }
        return null;
    }

    private function validateMax(string $field, mixed $value, int $max): ?string
    {
        if (is_numeric($value) && $value > $max) {
            return "The $field field must not be greater than $max.";
        }
        if (is_string($value) && mb_strlen($value) > $max) {
            return "The $field field must not be greater than $max characters.";
        }
        if (is_array($value) && count($value) > $max) {
            return "The $field field must not have more than $max items.";
        }
        return null;
    }

    private function validateIn(string $field, mixed $value, array $allowed): ?string
    {
        if (!in_array((string) $value, $allowed, true)) {
            $list = implode(', ', $allowed);
            return "The selected $field is invalid. Allowed values: $list.";
        }
        return null;
    }
}

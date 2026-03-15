<?php

declare(strict_types=1);

namespace Services;

/**
 * Immutable value object for validation outcomes
 *
 * Replaces two ad-hoc patterns:
 * - Array pattern: ['valid' => bool, 'error' => ?string]
 * - Object pattern: $validator->getErrors() returning string[]
 *
 * Usage:
 *   return ValidationResult::success();
 *   return ValidationResult::failure('Player not found');
 *   return ValidationResult::failures(['Error 1', 'Error 2']);
 */
final class ValidationResult
{
    /** @var list<string> */
    private array $errors;

    /** @param list<string> $errors */
    private function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    public static function success(): self
    {
        return new self([]);
    }

    public static function failure(string $error): self
    {
        return new self([$error]);
    }

    /** @param list<string> $errors */
    public static function failures(array $errors): self
    {
        return new self($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function getError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

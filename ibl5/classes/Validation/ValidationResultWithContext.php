<?php

declare(strict_types=1);

namespace Validation;

/**
 * Immutable validation outcome carrying structured errors plus a typed
 * context payload (post-trade cap totals, per-party deltas, ...).
 * Use ValidationResult when errors are plain strings and no context is needed.
 *
 * @template T
 */
final class ValidationResultWithContext
{
    /**
     * @param list<ValidationError> $errors
     * @param T $context
     */
    private function __construct(
        private readonly array $errors,
        private readonly mixed $context,
    ) {
    }

    /**
     * @template U
     * @param U $context
     * @return self<U>
     */
    public static function success(mixed $context = null): self
    {
        return new self([], $context);
    }

    /**
     * Empty $errors yields a valid result, matching ValidationResult::failures([]).
     *
     * @template U
     * @param list<ValidationError> $errors
     * @param U $context
     * @return self<U>
     */
    public static function fromErrors(array $errors, mixed $context = null): self
    {
        return new self($errors, $context);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<ValidationError> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function getErrorMessages(): array
    {
        return array_map(static fn (ValidationError $e): string => $e->message, $this->errors);
    }

    public function getFirstError(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }

    /** @return T */
    public function getContext(): mixed
    {
        return $this->context;
    }
}

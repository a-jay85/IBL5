<?php

declare(strict_types=1);

namespace Voting;

/**
 * Value object representing the outcome of a vote submission.
 *
 * Carries all validation errors (not just the first), so users see
 * every issue at once instead of fix-one-resubmit-find-next.
 */
final readonly class SubmissionResult
{
    /** @var list<string> */
    public array $errors;

    /**
     * @param bool $success Whether the submission was saved successfully
     * @param list<string> $errors Validation error messages (empty on success)
     */
    private function __construct(
        public bool $success,
        array $errors = [],
    ) {
        $this->errors = $errors;
    }

    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param list<string> $errors
     */
    public static function withErrors(array $errors): self
    {
        return new self(false, $errors);
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}

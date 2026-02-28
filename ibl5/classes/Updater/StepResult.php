<?php

declare(strict_types=1);

namespace Updater;

/**
 * Immutable value object representing the outcome of a single pipeline step.
 *
 * Use the named static factories to create instances:
 * - StepResult::success() — step completed normally
 * - StepResult::failure() — step encountered an error
 * - StepResult::skipped() — step was skipped (treated as a success)
 */
final class StepResult
{
    /**
     * @param list<string> $messages
     */
    private function __construct(
        public readonly string $label,
        public readonly bool $success,
        public readonly string $detail,
        public readonly string $capturedLog,
        public readonly string $inlineHtml,
        public readonly string $errorMessage,
        public readonly array $messages,
        public readonly int $messageErrorCount,
    ) {
    }

    /**
     * Create a successful step result.
     *
     * @param list<string> $messages
     */
    public static function success(
        string $label,
        string $detail = '',
        string $capturedLog = '',
        string $inlineHtml = '',
        array $messages = [],
        int $messageErrorCount = 0,
    ): self {
        return new self(
            label: $label,
            success: true,
            detail: $detail,
            capturedLog: $capturedLog,
            inlineHtml: $inlineHtml,
            errorMessage: '',
            messages: $messages,
            messageErrorCount: $messageErrorCount,
        );
    }

    /**
     * Create a failed step result.
     */
    public static function failure(string $label, string $errorMessage): self
    {
        return new self(
            label: $label,
            success: false,
            detail: '',
            capturedLog: '',
            inlineHtml: '',
            errorMessage: $errorMessage,
            messages: [],
            messageErrorCount: 0,
        );
    }

    /**
     * Create a skipped step result (treated as success with a reason).
     */
    public static function skipped(string $label, string $reason): self
    {
        return new self(
            label: $label,
            success: true,
            detail: $reason,
            capturedLog: '',
            inlineHtml: '',
            errorMessage: '',
            messages: [],
            messageErrorCount: 0,
        );
    }
}

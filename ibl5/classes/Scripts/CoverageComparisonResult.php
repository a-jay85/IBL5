<?php

declare(strict_types=1);

namespace Scripts;

final readonly class CoverageComparisonResult
{
    private function __construct(
        private bool $passed,
        private float $current,
        private ?float $previous,
        private float $minimumAllowed,
        private string $message,
    ) {
    }

    public static function pass(float $current, ?float $previous, float $minimumAllowed, string $message): self
    {
        return new self(true, $current, $previous, $minimumAllowed, $message);
    }

    public static function fail(float $current, ?float $previous, float $minimumAllowed, string $message): self
    {
        return new self(false, $current, $previous, $minimumAllowed, $message);
    }

    public function passed(): bool
    {
        return $this->passed;
    }

    public function getCurrent(): float
    {
        return $this->current;
    }

    public function getPrevious(): ?float
    {
        return $this->previous;
    }

    public function getMinimumAllowed(): float
    {
        return $this->minimumAllowed;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

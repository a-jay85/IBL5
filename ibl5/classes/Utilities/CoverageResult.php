<?php

declare(strict_types=1);

namespace Utilities;

final readonly class CoverageResult
{
    private function __construct(
        private bool $passed,
        private float $percentage,
        private float $threshold,
        private string $message,
    ) {
    }

    public static function success(float $percentage, float $threshold): self
    {
        return new self(
            true,
            $percentage,
            $threshold,
            sprintf('Coverage %.2f%% meets threshold %.2f%%', $percentage, $threshold),
        );
    }

    public static function failure(float $percentage, float $threshold, string $message): self
    {
        return new self(false, $percentage, $threshold, $message);
    }

    public function passed(): bool
    {
        return $this->passed;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

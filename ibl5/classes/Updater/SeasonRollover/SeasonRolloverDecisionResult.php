<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

/**
 * Immutable result of a rollover decision.
 *
 * $targetYear and $targetPhase are non-null ONLY when $outcome is Advance.
 * $reason is always a human-readable, caller-renderable sentence.
 */
final class SeasonRolloverDecisionResult
{
    public function __construct(
        public readonly RolloverOutcome $outcome,
        public readonly string $reason,
        public readonly ?int $targetYear = null,
        public readonly ?string $targetPhase = null,
    ) {
    }

    public static function noOp(string $reason): self
    {
        return new self(RolloverOutcome::NoOp, $reason);
    }

    public static function halt(string $reason): self
    {
        return new self(RolloverOutcome::Halt, $reason);
    }

    public static function advance(int $targetYear, string $targetPhase, string $reason): self
    {
        return new self(RolloverOutcome::Advance, $reason, $targetYear, $targetPhase);
    }

    public function shouldWrite(): bool
    {
        return $this->outcome === RolloverOutcome::Advance;
    }
}

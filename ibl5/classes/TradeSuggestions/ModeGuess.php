<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A guessed mode with the score and inputs it came from. score >= 0 is Win now.
 */
final readonly class ModeGuess
{
    public function __construct(
        public TeamMode $mode,
        public float $score,
        public float $recordPct,
        public ModeInputs $inputs,
    ) {
    }
}

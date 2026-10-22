<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A team's effective mode, the mode the guesser picks, whether the effective
 * mode comes from a GM override, and the guess details behind it.
 */
final readonly class ResolvedTeamMode
{
    public function __construct(
        public TeamMode $mode,
        public TeamMode $guessed,
        public bool $isOverride,
        public ?ModeGuess $guess = null,
    ) {
    }
}

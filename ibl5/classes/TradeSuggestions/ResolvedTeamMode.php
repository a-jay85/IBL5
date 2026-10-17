<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * A team's effective mode, the mode the guesser picks from record and age,
 * and whether the effective mode comes from a GM override.
 */
final readonly class ResolvedTeamMode
{
    public function __construct(
        public TeamMode $mode,
        public TeamMode $guessed,
        public bool $isOverride,
    ) {
    }
}

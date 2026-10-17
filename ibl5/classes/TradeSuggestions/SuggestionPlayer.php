<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Public roster facts about one player, reduced to what the suggestion scorer reads.
 *
 * quality = talent*2 + skill*2 + intangibles (each a 1-5 rating, so 5-25).
 * Salary fields are in thousands and are scoring approximations only; real cap
 * legality is decided by the Trading validator.
 */
final readonly class SuggestionPlayer
{
    public function __construct(
        public int $pid,
        public string $name,
        public string $pos,
        public int $age,
        public int $quality,
        public bool $injured,
        public int $currentSalary,
        public int $yearsRemaining,
        public int $futureSalary,
    ) {
    }
}

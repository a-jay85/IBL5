<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Public roster facts about one player, reduced to what the suggestion scorer reads.
 *
 * ovr is PlayerRating::overall() (0-100). Salary fields are in thousands and
 * describe the effective contract year (next season during the offseason
 * phases, as on the Trading form). yearsRemaining counts that year, so 1 means
 * the deal ends after it. Real cap legality is decided by the Trading validator.
 */
final readonly class SuggestionPlayer
{
    public function __construct(
        public int $pid,
        public string $name,
        public string $pos,
        public int $age,
        public int $ovr,
        public bool $injured,
        public int $currentSalary,
        public int $yearsRemaining,
        public int $futureSalary,
        public bool $tradeable = true,
    ) {
    }
}

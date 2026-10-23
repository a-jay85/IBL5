<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Public roster facts about one player, reduced to what the suggestion scorer reads.
 *
 * Salary fields are in thousands and describe the effective contract year (next season during the offseason
 * phases, as on the Trading form). yearsRemaining counts that year, so 1 means
 * the deal ends after it. Real cap legality is decided by the Trading validator.
 *
 * depth is the GM's depth chart (dc_*_depth, keyed PG SG SF PF C; 1 = starter).
 * production is the season line chosen by ProductionLine::choose(), or null
 * when neither season has enough minutes.
 */
final readonly class SuggestionPlayer
{
    public const array SLOTS = ['PG', 'SG', 'SF', 'PF', 'C'];

    /**
     * @param array<string, int> $depth
     * @param list<int> $laterSalaries Salary for effective year +1, +2, ... through cyt
     */
    public function __construct(
        public int $pid,
        public string $name,
        public string $pos,
        public int $age,
        public bool $injured,
        public int $currentSalary,
        public int $yearsRemaining,
        public int $futureSalary,
        public bool $tradeable = true,
        public bool $countsOnRoster = true,
        public array $depth = [],
        public int $dcMinutes = 0,
        public int $exp = 0,
        public ?ProductionLine $production = null,
        public array $laterSalaries = [],
    ) {
    }

    /**
     * The first slot (PG, SG, SF, PF, C order) where the depth chart starts this player.
     */
    public function starterSlot(): ?string
    {
        foreach (self::SLOTS as $slot) {
            if (($this->depth[$slot] ?? 0) === 1) {
                return $slot;
            }
        }

        return null;
    }

    public function hasProduction(): bool
    {
        return $this->production !== null;
    }
}

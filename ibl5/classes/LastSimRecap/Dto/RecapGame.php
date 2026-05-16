<?php

declare(strict_types=1);

namespace LastSimRecap\Dto;

final class RecapGame
{
    /**
     * @param list<int> $margins Per-quarter point differential from the team's POV (4 or 5 entries)
     * @param list<string> $qLabels Quarter labels matching $margins (['Q1','Q2','Q3','Q4'] or +['OT'])
     * @param list<RecapInjury> $yourInjuries
     * @param list<RecapInjury> $oppInjuries
     * @param list<RecapStarter> $starters
     */
    public function __construct(
        public readonly int $schedId,
        public readonly string $date,
        public readonly bool $home,
        public readonly bool $won,
        public readonly int $yourScore,
        public readonly int $oppScore,
        public readonly int $margin,
        public readonly bool $ot,
        public readonly array $margins,
        public readonly array $qLabels,
        public readonly int $oppTid,
        public readonly string $oppCity,
        public readonly string $oppName,
        public readonly string $oppCode,
        public readonly int $oppPreWins,
        public readonly int $oppPreLosses,
        public readonly array $yourInjuries,
        public readonly array $oppInjuries,
        public readonly array $starters,
    ) {}

    public function hasNewYourInjury(): bool
    {
        foreach ($this->yourInjuries as $i) {
            if ($i->isNew) {
                return true;
            }
        }
        return false;
    }

    public function hasNewOppInjury(): bool
    {
        foreach ($this->oppInjuries as $i) {
            if ($i->isNew) {
                return true;
            }
        }
        return false;
    }
}

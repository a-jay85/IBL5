<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Profile\TeamProfile;

/**
 * One team at one offset, before or after a deal. Plain values only.
 */
final readonly class TeamState
{
    /**
     * @param array<string, ?int> $starterRank slot => rank of the team's starter, null for an empty slot
     * @param array<string, ?int> $bestBenchRank slot => rank of the team's best bench player
     * @param array<string, SlotStrength> $positionStrength slot => strength
     */
    public function __construct(
        public int $tid,
        public int $offset,
        public SeasonLineup $lineup,
        public array $starterRank,
        public array $bestBenchRank,
        public TeamProfile $profile,
        public array $positionStrength,
        public int $payroll,
        public int $payrollRank,
        public int $rosterCount,
    ) {
    }
}

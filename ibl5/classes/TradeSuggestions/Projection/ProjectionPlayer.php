<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

use TradeSuggestions\ProductionLine;
use TradeSuggestions\SlotMapper;

/**
 * The input value object `ProjectionPlayerRepository` builds for each player.
 *
 * `productionFouls` carries the personal fouls of the chosen production line,
 * because v2's `ProductionLine` has no fouls field and stays unchanged.
 */
final readonly class ProjectionPlayer
{
    /**
     * @param array<string, int> $prefs loyalty, winner, tradition, security, playing_time
     * @param array<string, int> $depth PG..C from pg_depth..c_depth
     * @param array<string, int> $dcDepth PG..C from dc_pg_depth..dc_c_depth
     * @param array<int, int> $salaryByYear salary_yr1..6 keyed 1..6
     */
    public function __construct(
        public int $pid,
        public string $name,
        public string $pos,
        public int $tid,
        public int $age,
        public int $injuredDays,
        public int $exp,
        public int $talent,
        public int $bird,
        public int $draftRound,
        public int $draftYear,
        public int $draftPickNo,
        public array $prefs,
        public RatingSet $ratings,
        public array $depth,
        public array $dcDepth,
        public int $dcMinutes,
        public int $effectiveCy,
        public array $salaryByYear,
        public ?ProductionLine $production,
        public bool $tradeable,
        public int $productionFouls = 0,
    ) {
    }

    /**
     * Salary at horizon offset k is salary_yr{effectiveCy + k}; 0 past the
     * contract end or beyond salary_yr6.
     */
    public function salaryAt(int $offset): int
    {
        return $this->salaryByYear[$this->effectiveCy + $offset] ?? 0;
    }

    public function onRosterAt(int $offset): bool
    {
        return $this->salaryAt($offset) > 0;
    }

    public function ageAt(int $offset): int
    {
        return $this->age + $offset;
    }

    /**
     * @return list<string>
     */
    public function slots(): array
    {
        return SlotMapper::slotsForPos($this->pos);
    }
}

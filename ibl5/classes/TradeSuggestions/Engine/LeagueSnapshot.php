<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Profile\TeamProfile;
use TradeSuggestions\Profile\TeamSituation;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Valuation\DraftSlotIndex;
use TradeSuggestions\Valuation\FairSalaryTable;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * One immutable league state, built once per league state and cached. Plain
 * value fields keyed by tid and offset. It holds no database handle and no
 * per-team facade; consumers index the arrays.
 *
 * @phpstan-import-type ProjectedDraftOrderResult from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 * @phpstan-type StandingsRow array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}
 * @phpstan-type StreakRow array{last_win: int, last_loss: int}
 */
final readonly class LeagueSnapshot
{
    /**
     * @param list<int> $teamIds real franchises present in the loaded rosters
     * @param array<int, ProjectionPlayer> $players pid => player
     * @param array<int, int> $teamOfPid pid => tid
     * @param array<int, array<int, ProjectedSeason>> $projections pid => offset => season
     * @param array<int, array<int, SeasonLineup>> $lineups offset => tid => lineup
     * @param array<int, SlotRankState> $slotRanks offset => rank state
     * @param array<int, array<int, TeamProfile>> $profiles offset => tid => profile
     * @param array<int, array<int, array<string, SlotStrength>>> $positionStrength offset => tid => slot => strength
     * @param array<int, TeamSituation> $situations tid => situation
     * @param array<int, StandingsRow> $standings tid => standings row
     * @param array<int, StreakRow> $streaks tid => streak row
     * @param array<int, FairSalaryTable> $fairSalary offset => table
     * @param array<int, array<int, int>> $cashByTeamAndOffset tid => offset => cash
     * @param array<int, array<int, int>> $payrollByOffset offset => tid => payroll
     * @param array<int, array<int, int>> $payrollRanks offset => tid => rank (1 = lowest payroll)
     * @param array<int, PickAsset> $picks pickid => pick
     * @param ProjectedDraftOrderResult $draftOrder
     * @param array<int, TeamMode> $modes tid => mode
     * @param array<int, InjuryStatus> $injury pid => status
     * @param array<int, TeamFactors> $teamFactors tid => factors
     * @param array<int, float> $resignLikelihood pid => likelihood against his current team
     * @param array<int, bool> $likelyToWalk pid => walker flag
     * @param array<int, string> $teamNames tid => name
     */
    public function __construct(
        public int $seasonYear,
        public bool $advancesContractYears,
        public bool $regularSeason,
        public array $teamIds,
        public array $players,
        public array $teamOfPid,
        public array $projections,
        public array $lineups,
        public array $slotRanks,
        public array $profiles,
        public array $positionStrength,
        public array $situations,
        public array $standings,
        public array $streaks,
        public array $fairSalary,
        public array $cashByTeamAndOffset,
        public array $payrollByOffset,
        public array $payrollRanks,
        public array $picks,
        public array $draftOrder,
        public DraftSlotIndex $draftIndex,
        public array $modes,
        public array $injury,
        public array $teamFactors,
        public TeamFactors $rivalFactors,
        public array $resignLikelihood,
        public array $likelyToWalk,
        public array $teamNames,
    ) {
    }

    public function mode(int $tid): TeamMode
    {
        return $this->modes[$tid] ?? throw new \OutOfRangeException("No mode for team {$tid}");
    }

    public function lineup(int $offset, int $tid): SeasonLineup
    {
        return $this->lineups[$offset][$tid] ?? throw new \OutOfRangeException("No lineup for team {$tid} at offset {$offset}");
    }

    /**
     * Contract surplus per offset, priced at the fair salary of the player's
     * best pool-rank tier (D9). An offset where he holds no pool rank is left
     * out, so `ContractValue` returns 0 there.
     *
     * @return array<int, int> offset => surplus
     */
    public function surplusBySeason(int $pid): array
    {
        $salaryByOffset = [];
        $fairByOffset = [];
        foreach ($this->slotRanks as $offset => $ranks) {
            $bestSlot = null;
            $bestRank = null;
            foreach (SlotMapper::SLOTS as $slot) {
                $rank = $ranks->poolRank($slot, $pid);
                if ($rank !== null && ($bestRank === null || $rank < $bestRank)) {
                    $bestRank = $rank;
                    $bestSlot = $slot;
                }
            }
            $season = $this->projections[$pid][$offset] ?? null;
            if ($bestSlot === null || $bestRank === null || $season === null || !isset($this->fairSalary[$offset])) {
                continue;
            }
            $salaryByOffset[$offset] = $season->salary;
            $fairByOffset[$offset] = $this->fairSalary[$offset]->fairSalary($bestSlot, FairSalaryTable::tierFor($bestRank));
        }

        return ContractValue::surplusBySeason($salaryByOffset, $fairByOffset);
    }
}

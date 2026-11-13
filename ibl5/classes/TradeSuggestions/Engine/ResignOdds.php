<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\Valuation\FairSalaryTable;
use TradeSuggestions\Valuation\ResignLikelihood;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * Re-sign odds for one player against one team (F10). The builder calls it for
 * his current team; Phase 7 calls it again for a team that receives him.
 */
final class ResignOdds
{
    /**
     * @param array<int, SeasonLineup> $offsetZeroLineups tid => offset-0 lineup
     * @param array<int, TeamFactors> $teamFactors tid => factors
     * @return array{likelihood: float, walks: bool}
     */
    public static function forTeam(
        ProjectionPlayer $p,
        int $tid,
        array $offsetZeroLineups,
        SlotRankReaderInterface $ranksAtZero,
        array $teamFactors,
    ): array {
        $bestSlot = null;
        $bestRank = null;
        foreach (SlotMapper::SLOTS as $slot) {
            $rank = $ranksAtZero->poolRank($slot, $p->pid);
            if ($rank !== null && ($bestRank === null || $rank < $bestRank)) {
                $bestRank = $rank;
                $bestSlot = $slot;
            }
        }
        $slot = $bestSlot ?? ($p->slots()[0] ?? SlotMapper::SLOTS[0]);

        $own = 0;
        $rivalTotal = 0;
        $rivalTeams = 0;
        foreach ($offsetZeroLineups as $lineupTid => $lineup) {
            $money = self::positionMoney($lineup, $slot, $p->pid);
            if ($lineupTid === $tid) {
                $own = $money;
            } else {
                $rivalTotal += $money;
                $rivalTeams++;
            }
        }
        $rival = $rivalTeams === 0 ? 0 : intdiv($rivalTotal, $rivalTeams);

        $edge = ResignLikelihood::edge(
            $p->prefs,
            $p->bird,
            $teamFactors[$tid] ?? new TeamFactors($tid, 0, 0, 0, 0),
            TeamFactors::average($teamFactors),
            $own,
            $rival,
        );
        $likelihood = ResignLikelihood::likelihood($edge);

        $seasonsLeft = 0;
        foreach (Horizon::OFFSETS as $offset) {
            if ($p->salaryAt($offset) > 0) {
                $seasonsLeft++;
            }
        }
        $walks = $bestRank !== null
            && ResignLikelihood::isLikelyToWalk($likelihood, FairSalaryTable::tierFor($bestRank), $seasonsLeft);

        return ['likelihood' => $likelihood, 'walks' => $walks];
    }

    private static function positionMoney(SeasonLineup $lineup, string $slot, int $excludePid): int
    {
        $sum = 0;
        foreach ($lineup->players as $pid => $player) {
            if ($pid !== $excludePid && ($player->slots()[0] ?? null) === $slot) {
                $sum += $player->salaryAt(0);
            }
        }

        return $sum;
    }
}

<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;

/**
 * Sorts teams by record for draft order (worst first), resolving ties via
 * aggregate head-to-head and non-H2H tiebreakers.
 *
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class DraftOrderTiebreakerResolver
{
    private NonHeadToHeadTiebreaker $nonH2hTiebreaker;

    public function __construct(NonHeadToHeadTiebreaker $nonH2hTiebreaker)
    {
        $this->nonH2hTiebreaker = $nonH2hTiebreaker;
    }

    /**
     * Sort teams by record for draft order (worst first).
     *
     * Tiebreaker loser gets the earlier (better) pick in both lottery and playoff contexts.
     * Uses multi-way aggregate H2H for groups of 3+ teams with the same pct.
     *
     * @param list<StandingsRow> $teams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return list<StandingsRow>
     */
    public function sortTeamsByRecord(array $teams, array $h2h, array $pointDiffs): array
    {
        // Step 1: Sort by pct ascending (worst first)
        usort($teams, static fn (array $a, array $b): int => $a['pct'] <=> $b['pct']);

        // Step 2: Resolve tied groups using multi-way aggregate H2H
        return $this->resolveTiedGroups($teams, $h2h, $pointDiffs);
    }

    /**
     * Walk the pct-sorted list, identify consecutive same-pct groups, and sort each.
     *
     * @param list<StandingsRow> $teams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return list<StandingsRow>
     */
    private function resolveTiedGroups(array $teams, array $h2h, array $pointDiffs): array
    {
        $result = [];
        $i = 0;
        $count = count($teams);

        while ($i < $count) {
            $groupStart = $i;
            $currentPct = $teams[$i]['pct'];

            // Find end of consecutive same-pct group
            while ($i < $count && $teams[$i]['pct'] === $currentPct) {
                $i++;
            }

            $group = array_slice($teams, $groupStart, $i - $groupStart);

            if (count($group) > 1) {
                $group = $this->sortTiedGroup($group, $h2h, $pointDiffs);
            }

            array_push($result, ...$group);
        }

        return $result;
    }

    /**
     * Sort a group of teams with the same pct using aggregate H2H then fallback tiebreakers.
     *
     * For draft order: worse aggregate H2H → earlier (better) pick.
     *
     * @param list<StandingsRow> $group
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return list<StandingsRow>
     */
    private function sortTiedGroup(array $group, array $h2h, array $pointDiffs): array
    {
        $tids = array_map(static fn (array $t): int => $t['teamid'], $group);

        $aggregateH2HPct = \Standings\AggregateTiebreaker::computeAggregateH2HPcts(
            $tids,
            /** @return array{wins: int, losses: int} */
            static fn (int $tid, int $oppTid): array => [
                'wins' => $h2h[$tid][$oppTid] ?? 0,
                'losses' => $h2h[$oppTid][$tid] ?? 0,
            ],
        );

        usort($group, function (array $a, array $b) use ($aggregateH2HPct, $pointDiffs): int {
            $h2hDiff = $aggregateH2HPct[$a['teamid']] <=> $aggregateH2HPct[$b['teamid']];
            if ($h2hDiff !== 0) {
                return $h2hDiff;
            }

            return -$this->nonH2hTiebreaker->applyNonH2HTiebreakers($a, $b, $pointDiffs);
        });

        return $group;
    }
}

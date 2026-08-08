<?php

declare(strict_types=1);

namespace Standings;

/**
 * StandingsTiebreakerResolver - H2H tiebreaker logic for standings groups
 *
 * Resolves groups of teams tied on GB, clinch tier, and wins by sorting each
 * group by aggregate head-to-head win percentage.
 *
 * @phpstan-import-type StandingsRow from \Standings\Contracts\StandingsRepositoryInterface
 */
final class StandingsTiebreakerResolver
{
    /**
     * Resolve H2H tie-breaking for groups of teams tied on GB, clinch tier, and wins
     *
     * Walks the sorted standings list, identifies groups of teams with the same
     * games-back, clinch tier, and wins, then sorts each group by aggregate H2H
     * win percentage (best H2H first for standings).
     *
     * @param list<StandingsRow> $teams Standings sorted by SQL (GB, clinch, wins)
     * @param array<int, array<int, array{wins: int, losses: int}>>|null $seriesMatrix Pre-loaded H2H series matrix
     * @return list<StandingsRow> Re-sorted standings with H2H tie-breaking applied
     */
    public function resolveH2HTiedGroups(array $teams, ?array $seriesMatrix): array
    {
        if (count($teams) <= 1 || $seriesMatrix === null || $seriesMatrix === []) {
            return $teams;
        }

        /** @var list<StandingsRow> $result */
        $result = [];
        $count = count($teams);
        $groupStart = 0;

        for ($i = 1; $i <= $count; $i++) {
            if ($i < $count
                && $teams[$i]['gamesBack'] === $teams[$groupStart]['gamesBack']
                && StandingsRowView::getClinchTierScore($teams[$i]) === StandingsRowView::getClinchTierScore($teams[$groupStart])
                && $teams[$i]['wins'] === $teams[$groupStart]['wins']
            ) {
                continue;
            }

            $group = array_slice($teams, $groupStart, $i - $groupStart);

            if (count($group) > 1) {
                $group = $this->sortTiedGroup($group, $seriesMatrix);
            }

            array_push($result, ...$group);
            $groupStart = $i;
        }

        return $result;
    }

    /**
     * Sort a tied group by aggregate H2H win percentage (best first for standings)
     *
     * For each team, computes aggregate H2H record against all other teams in the
     * group, then sorts descending by H2H win pct.
     *
     * @param list<StandingsRow> $group Teams tied on GB/clinch/wins
     * @param array<int, array<int, array{wins: int, losses: int}>> $seriesMatrix Pre-loaded H2H series matrix
     * @return list<StandingsRow> Sorted group (best H2H first)
     */
    private function sortTiedGroup(array $group, array $seriesMatrix): array
    {
        $tids = array_map(static fn (array $t): int => $t['teamid'], $group);
        $matrix = $seriesMatrix;

        $aggregateH2HPct = AggregateTiebreaker::computeAggregateH2HPcts(
            $tids,
            /** @return array{wins: int, losses: int} */
            static fn (int $tid, int $oppTid): array => [
                'wins' => $matrix[$tid][$oppTid]['wins'] ?? 0,
                'losses' => $matrix[$tid][$oppTid]['losses'] ?? 0,
            ],
        );

        usort($group, static function (array $a, array $b) use ($aggregateH2HPct): int {
            return $aggregateH2HPct[$b['teamid']] <=> $aggregateH2HPct[$a['teamid']];
        });

        return $group;
    }
}

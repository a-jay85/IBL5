<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;

/**
 * Determines playoff teams and compares teams for playoff seeding (best team first).
 *
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class PlayoffSeedingCalculator
{
    private NonHeadToHeadTiebreaker $nonH2hTiebreaker;

    public function __construct(NonHeadToHeadTiebreaker $nonH2hTiebreaker)
    {
        $this->nonH2hTiebreaker = $nonH2hTiebreaker;
    }

    /**
     * Determine playoff teams, division winners, conference winner, and non-playoff teams.
     *
     * @param list<StandingsRow> $conferenceTeams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return array{wildCards: list<StandingsRow>, divisionWinners: list<StandingsRow>, conferenceWinner: StandingsRow|null, nonPlayoff: list<StandingsRow>}
     */
    public function determinePlayoffTeams(array $conferenceTeams, array $h2h, array $pointDiffs): array
    {
        if ($conferenceTeams === []) {
            return ['wildCards' => [], 'divisionWinners' => [], 'conferenceWinner' => null, 'nonPlayoff' => []];
        }

        $byDivision = [];
        foreach ($conferenceTeams as $team) {
            $byDivision[$team['division']][] = $team;
        }

        $divisionWinners = [];
        $nonWinners = [];

        foreach ($byDivision as $divisionTeams) {
            usort($divisionTeams, fn (array $a, array $b): int => $this->compareTeamsForPlayoffSeeding($a, $b, $h2h, $pointDiffs));
            $divisionWinners[] = $divisionTeams[0];
            for ($i = 1; $i < count($divisionTeams); $i++) {
                $nonWinners[] = $divisionTeams[$i];
            }
        }

        // Sort non-winners by record (best first) to pick top 6
        usort($nonWinners, fn (array $a, array $b): int => $this->compareTeamsForPlayoffSeeding($a, $b, $h2h, $pointDiffs));

        $wildCards = array_slice($nonWinners, 0, 6);
        $nonPlayoff = array_slice($nonWinners, 6);

        // Conference winner is the best division winner
        usort($divisionWinners, fn (array $a, array $b): int => $this->compareTeamsForPlayoffSeeding($a, $b, $h2h, $pointDiffs));
        $conferenceWinner = $divisionWinners[0];
        $divisionOnlyWinners = array_slice($divisionWinners, 1);

        return [
            'wildCards' => array_values($wildCards),
            'divisionWinners' => array_values($divisionOnlyWinners),
            'conferenceWinner' => $conferenceWinner,
            'nonPlayoff' => array_values($nonPlayoff),
        ];
    }

    /**
     * Compare two teams for playoff seeding (best team first).
     *
     * @param StandingsRow $a
     * @param StandingsRow $b
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     */
    private function compareTeamsForPlayoffSeeding(array $a, array $b, array $h2h, array $pointDiffs): int
    {
        // Higher pct is better (sort descending)
        $pctDiff = $b['pct'] <=> $a['pct'];
        if ($pctDiff !== 0) {
            return $pctDiff;
        }

        return $this->applyTiebreakers($a, $b, $h2h, $pointDiffs);
    }

    /**
     * Apply NBA tiebreakers between two teams (pairwise H2H + remaining tiebreakers).
     *
     * Used by playoff seeding. Draft order uses multi-way aggregate H2H instead.
     *
     * Returns negative if A wins the tiebreaker, positive if B wins.
     *
     * @param StandingsRow $a
     * @param StandingsRow $b
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     */
    private function applyTiebreakers(array $a, array $b, array $h2h, array $pointDiffs): int
    {
        // 1. Head-to-head record (pairwise)
        $aWinsVsB = $h2h[$a['teamid']][$b['teamid']] ?? 0;
        $bWinsVsA = $h2h[$b['teamid']][$a['teamid']] ?? 0;
        if ($aWinsVsB !== $bWinsVsA) {
            return $bWinsVsA <=> $aWinsVsB;
        }

        // 2-6. Non-H2H tiebreakers
        return $this->nonH2hTiebreaker->applyNonH2HTiebreakers($a, $b, $pointDiffs);
    }
}

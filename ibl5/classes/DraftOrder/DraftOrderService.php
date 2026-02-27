<?php

declare(strict_types=1);

namespace DraftOrder;

use DraftOrder\Contracts\DraftOrderRepositoryInterface;
use DraftOrder\Contracts\DraftOrderServiceInterface;

/**
 * Calculates projected draft order based on current standings.
 *
 * @phpstan-import-type StandingsRow from DraftOrderRepositoryInterface
 * @phpstan-import-type GameRow from DraftOrderRepositoryInterface
 * @phpstan-import-type PointDifferentialRow from DraftOrderRepositoryInterface
 * @phpstan-import-type PickOwnershipRow from DraftOrderRepositoryInterface
 * @phpstan-import-type DraftSlot from DraftOrderServiceInterface
 * @phpstan-import-type DraftOrderResult from DraftOrderServiceInterface
 * @see DraftOrderRepositoryInterface For data access
 */
class DraftOrderService implements DraftOrderServiceInterface
{
    private DraftOrderRepositoryInterface $repository;

    public function __construct(DraftOrderRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /** @return DraftOrderResult */
    public function calculateDraftOrder(int $seasonYear): array
    {
        $standings = $this->repository->getAllTeamsWithStandings();
        $games = $this->repository->getPlayedGames($seasonYear);
        $pickOwnershipRows = $this->repository->getPickOwnership($seasonYear);
        $pointDiffRows = $this->repository->getPointDifferentials($seasonYear);

        $h2h = $this->buildHeadToHeadMatrix($games);
        $pointDiffs = $this->buildPointDifferentialMap($pointDiffRows);

        $teamsByConference = $this->groupByConference($standings);
        $playoffTeamIds = [];
        $nonPlayoffTeamIds = [];

        foreach (\League::CONFERENCE_NAMES as $conference) {
            $conferenceTeams = $teamsByConference[$conference] ?? [];
            $result = $this->determinePlayoffTeams($conferenceTeams, $h2h, $pointDiffs);
            foreach ($result['playoff'] as $team) {
                $playoffTeamIds[] = $team['tid'];
            }
            foreach ($result['nonPlayoff'] as $team) {
                $nonPlayoffTeamIds[] = $team['tid'];
            }
        }

        $teamMap = $this->buildTeamMap($standings);

        $nonPlayoffTeams = array_map(
            static fn (int $tid): array => $teamMap[$tid],
            $nonPlayoffTeamIds
        );
        $playoffTeams = array_map(
            static fn (int $tid): array => $teamMap[$tid],
            $playoffTeamIds
        );

        $nonPlayoffSorted = $this->sortTeamsByRecord($nonPlayoffTeams, $h2h, $pointDiffs);
        $playoffSorted = $this->sortTeamsByRecord($playoffTeams, $h2h, $pointDiffs);

        $round1Order = array_merge($nonPlayoffSorted, $playoffSorted);

        $allTeamsSorted = $this->sortTeamsByRecord(array_values($teamMap), $h2h, $pointDiffs);

        $pickOwnership = $this->buildPickOwnershipMap($pickOwnershipRows);

        $round1 = $this->buildRound($round1Order, $pickOwnership, 1, $teamMap);
        $round2 = $this->buildRound($allTeamsSorted, $pickOwnership, 2, $teamMap);

        return ['round1' => $round1, 'round2' => $round2];
    }

    /**
     * Build head-to-head win matrix from played games.
     *
     * @param list<GameRow> $games
     * @return array<int, array<int, int>> h2h[tidA][tidB] = wins by A vs B
     */
    private function buildHeadToHeadMatrix(array $games): array
    {
        $h2h = [];
        foreach ($games as $game) {
            $visitor = $game['Visitor'];
            $home = $game['Home'];
            if ($game['VScore'] > $game['HScore']) {
                $h2h[$visitor][$home] = ($h2h[$visitor][$home] ?? 0) + 1;
            } else {
                $h2h[$home][$visitor] = ($h2h[$home][$visitor] ?? 0) + 1;
            }
        }
        return $h2h;
    }

    /**
     * @param list<PointDifferentialRow> $rows
     * @return array<int, float> tid => net points (pointsFor - pointsAgainst)
     */
    private function buildPointDifferentialMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['tid']] = $row['pointsFor'] - $row['pointsAgainst'];
        }
        return $map;
    }

    /**
     * @param list<StandingsRow> $standings
     * @return array<string, list<StandingsRow>>
     */
    private function groupByConference(array $standings): array
    {
        $grouped = [];
        foreach ($standings as $team) {
            $grouped[$team['conference']][] = $team;
        }
        return $grouped;
    }

    /**
     * @param list<StandingsRow> $standings
     * @return array<int, StandingsRow>
     */
    private function buildTeamMap(array $standings): array
    {
        $map = [];
        foreach ($standings as $team) {
            $map[$team['tid']] = $team;
        }
        return $map;
    }

    /**
     * Determine 8 playoff teams and 6 non-playoff teams for a conference.
     *
     * @param list<StandingsRow> $conferenceTeams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return array{playoff: list<StandingsRow>, nonPlayoff: list<StandingsRow>}
     */
    private function determinePlayoffTeams(array $conferenceTeams, array $h2h, array $pointDiffs): array
    {
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

        $playoff = array_merge($divisionWinners, $wildCards);

        return [
            'playoff' => array_values($playoff),
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

        return $this->applyTiebreakers($a, $b, $h2h, $pointDiffs, 'better_wins');
    }

    /**
     * Sort teams by record for draft order (worst first).
     *
     * Tiebreaker loser gets the earlier (better) pick in both lottery and playoff contexts.
     *
     * @param list<StandingsRow> $teams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return list<StandingsRow>
     */
    private function sortTeamsByRecord(array $teams, array $h2h, array $pointDiffs): array
    {
        usort($teams, function (array $a, array $b) use ($h2h, $pointDiffs): int {
            // Ascending pct (worst first)
            $pctDiff = $a['pct'] <=> $b['pct'];
            if ($pctDiff !== 0) {
                return $pctDiff;
            }

            // applyTiebreakers returns negative if A wins (better team).
            // For draft order: tiebreaker LOSER gets earlier pick.
            // Negate so the winner sorts later (higher index = later pick).
            return -$this->applyTiebreakers($a, $b, $h2h, $pointDiffs, 'better_wins');
        });

        return $teams;
    }

    /**
     * Apply NBA tiebreakers between two teams.
     *
     * Returns negative if A wins the tiebreaker, positive if B wins.
     *
     * @param StandingsRow $a
     * @param StandingsRow $b
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @param 'better_wins' $direction
     */
    private function applyTiebreakers(array $a, array $b, array $h2h, array $pointDiffs, string $direction): int
    {
        // 1. Head-to-head record
        $aWinsVsB = $h2h[$a['tid']][$b['tid']] ?? 0;
        $bWinsVsA = $h2h[$b['tid']][$a['tid']] ?? 0;
        if ($aWinsVsB !== $bWinsVsA) {
            return $direction === 'better_wins' ? ($bWinsVsA <=> $aWinsVsB) : ($aWinsVsB <=> $bWinsVsA);
        }

        // 2. Division winner status
        $aDivWinner = ($a['clinchedDivision'] ?? 0) === 1;
        $bDivWinner = ($b['clinchedDivision'] ?? 0) === 1;
        if ($aDivWinner !== $bDivWinner) {
            if ($direction === 'better_wins') {
                return $aDivWinner ? -1 : 1;
            }
            return $bDivWinner ? -1 : 1;
        }

        // 3. Division record (same-division teams only)
        if ($a['division'] === $b['division']) {
            $aDivPct = $this->safeWinPct($a['divWins'], $a['divLosses']);
            $bDivPct = $this->safeWinPct($b['divWins'], $b['divLosses']);
            $divDiff = $bDivPct <=> $aDivPct;
            if ($divDiff !== 0) {
                return $direction === 'better_wins' ? $divDiff : -$divDiff;
            }
        }

        // 4. Conference record
        $aConfPct = $this->safeWinPct($a['confWins'], $a['confLosses']);
        $bConfPct = $this->safeWinPct($b['confWins'], $b['confLosses']);
        $confDiff = $bConfPct <=> $aConfPct;
        if ($confDiff !== 0) {
            return $direction === 'better_wins' ? $confDiff : -$confDiff;
        }

        // 5. Point differential
        $aNetPts = $pointDiffs[$a['tid']] ?? 0.0;
        $bNetPts = $pointDiffs[$b['tid']] ?? 0.0;
        $ptsDiff = $bNetPts <=> $aNetPts;
        if ($ptsDiff !== 0) {
            return $direction === 'better_wins' ? $ptsDiff : -$ptsDiff;
        }

        // Fallback: alphabetical by team name (for deterministic ordering)
        return $a['team_name'] <=> $b['team_name'];
    }

    private function safeWinPct(?int $wins, ?int $losses): float
    {
        $w = $wins ?? 0;
        $l = $losses ?? 0;
        $total = $w + $l;
        if ($total === 0) {
            return 0.0;
        }
        return $w / $total;
    }

    /**
     * Build pick ownership map: pickOwnership[teamName][round] = {ownerName, notes}
     *
     * @param list<PickOwnershipRow> $rows
     * @return array<string, array<int, array{ownerName: string, notes: string}>>
     */
    private function buildPickOwnershipMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['teampick']][$row['round']] = [
                'ownerName' => $row['ownerofpick'],
                'notes' => $row['notes'] ?? '',
            ];
        }
        return $map;
    }

    /**
     * Build a round of draft slots with ownership overlay.
     *
     * @param list<StandingsRow> $baseOrder
     * @param array<string, array<int, array{ownerName: string, notes: string}>> $pickOwnership
     * @param int $round
     * @param array<int, StandingsRow> $teamMap
     * @return list<DraftSlot>
     */
    private function buildRound(array $baseOrder, array $pickOwnership, int $round, array $teamMap): array
    {
        $slots = [];
        $nameToIdMap = [];
        foreach ($teamMap as $tid => $team) {
            $nameToIdMap[$team['team_name']] = $tid;
        }

        foreach ($baseOrder as $index => $team) {
            $pickNumber = $index + 1;
            $teamName = $team['team_name'];

            $ownership = $pickOwnership[$teamName][$round] ?? null;
            $ownerName = $ownership !== null ? $ownership['ownerName'] : $teamName;
            $notes = $ownership !== null ? $ownership['notes'] : '';
            $isTraded = $ownerName !== $teamName;

            $ownerId = $nameToIdMap[$ownerName] ?? $team['tid'];
            $ownerTeam = $teamMap[$ownerId] ?? $team;

            $slots[] = [
                'pick' => $pickNumber,
                'teamId' => $team['tid'],
                'teamName' => $teamName,
                'wins' => $team['wins'],
                'losses' => $team['losses'],
                'color1' => $team['color1'],
                'color2' => $team['color2'],
                'ownerId' => $ownerId,
                'ownerName' => $ownerName,
                'ownerColor1' => $ownerTeam['color1'],
                'ownerColor2' => $ownerTeam['color2'],
                'isTraded' => $isTraded,
                'notes' => $notes,
            ];
        }

        return $slots;
    }
}

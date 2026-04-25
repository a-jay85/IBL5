<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use League\League;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;

/**
 * Calculates projected draft order based on current standings.
 *
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 * @phpstan-import-type GameRow from ProjectedDraftOrderRepositoryInterface
 * @phpstan-import-type PointDifferentialRow from ProjectedDraftOrderRepositoryInterface
 * @phpstan-import-type PickOwnershipRow from ProjectedDraftOrderRepositoryInterface
 * @phpstan-import-type DraftSlot from ProjectedDraftOrderServiceInterface
 * @phpstan-import-type ProjectedDraftOrderResult from ProjectedDraftOrderServiceInterface
 * @see ProjectedDraftOrderRepositoryInterface For data access
 */
class ProjectedDraftOrderService implements ProjectedDraftOrderServiceInterface
{
    private const LOTTERY_SIZE = 12;

    private ProjectedDraftOrderRepositoryInterface $repository;

    public function __construct(ProjectedDraftOrderRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /** @return ProjectedDraftOrderResult */
    public function calculateDraftOrder(int $seasonYear): array
    {
        $standings = $this->repository->getAllTeamsWithStandings();

        if ($standings === []) {
            return ['round1' => [], 'round2' => []];
        }

        $games = $this->repository->getPlayedGames($seasonYear);
        $pickOwnershipRows = $this->repository->getPickOwnership($seasonYear);
        $pointDiffRows = $this->repository->getPointDifferentials($seasonYear);

        $h2h = $this->buildHeadToHeadMatrix($games);
        $pointDiffs = $this->buildPointDifferentialMap($pointDiffRows);

        $teamsByConference = $this->groupByConference($standings);
        $nonPlayoffTeams = [];
        $wildCardTeams = [];
        $divisionWinnerTeams = [];
        $conferenceWinnerTeams = [];

        foreach (League::CONFERENCE_NAMES as $conference) {
            $conferenceTeams = $teamsByConference[$conference] ?? [];
            $result = $this->determinePlayoffTeams($conferenceTeams, $h2h, $pointDiffs);
            array_push($nonPlayoffTeams, ...$result['nonPlayoff']);
            array_push($wildCardTeams, ...$result['wildCards']);
            array_push($divisionWinnerTeams, ...$result['divisionWinners']);
            if ($result['conferenceWinner'] !== null) {
                $conferenceWinnerTeams[] = $result['conferenceWinner'];
            }
        }

        $teamMap = $this->buildTeamMap($standings);

        $nonPlayoffSorted = $this->sortTeamsByRecord($nonPlayoffTeams, $h2h, $pointDiffs);
        $wildCardsSorted = $this->sortTeamsByRecord($wildCardTeams, $h2h, $pointDiffs);
        $divisionWinnersSorted = $this->sortTeamsByRecord($divisionWinnerTeams, $h2h, $pointDiffs);
        $conferenceWinnersSorted = $this->sortTeamsByRecord($conferenceWinnerTeams, $h2h, $pointDiffs);

        $round1Order = array_merge($nonPlayoffSorted, $wildCardsSorted, $divisionWinnersSorted, $conferenceWinnersSorted);

        $allTeamsSorted = $this->sortTeamsByRecord(array_values($teamMap), $h2h, $pointDiffs);

        $pickOwnership = $this->buildPickOwnershipMap($pickOwnershipRows);

        $round1 = $this->buildRound($round1Order, $pickOwnership, 1, $teamMap);
        $round2 = $this->buildRound($allTeamsSorted, $pickOwnership, 2, $teamMap);

        return ['round1' => $round1, 'round2' => $round2];
    }

    /** @return ProjectedDraftOrderResult */
    public function getFinalOrProjectedDraftOrder(int $seasonYear): array
    {
        if (!$this->repository->isDraftOrderFinalized()) {
            return $this->calculateDraftOrder($seasonYear);
        }

        $savedRound1 = $this->repository->getFinalDraftOrder($seasonYear, 1);
        if ($savedRound1 === []) {
            return $this->calculateDraftOrder($seasonYear);
        }

        $projected = $this->calculateDraftOrder($seasonYear);
        $standings = $this->repository->getAllTeamsWithStandings();
        $teamMap = $this->buildTeamMap($standings);
        $pickOwnershipRows = $this->repository->getPickOwnership($seasonYear);
        $pickOwnership = $this->buildPickOwnershipMap($pickOwnershipRows);

        $nameToIdMap = [];
        foreach ($teamMap as $teamid => $team) {
            $nameToIdMap[$team['team_name']] = $teamid;
        }

        // Build projected pick map: teamId => projected pick number (lottery only)
        $projectedPickByTeam = [];
        foreach ($projected['round1'] as $slot) {
            if ($slot['pick'] <= self::LOTTERY_SIZE) {
                $projectedPickByTeam[$slot['teamId']] = $slot['pick'];
            }
        }

        // Build round-1 from saved picks, enriching with standings and ownership data
        $round1 = $this->buildRoundFromSavedPicks($savedRound1, 1, $teamMap, $nameToIdMap, $pickOwnership, $projectedPickByTeam);

        // Build round-2 from saved picks
        $savedRound2 = $this->repository->getFinalDraftOrder($seasonYear, 2);
        $round2 = $savedRound2 !== []
            ? $this->buildRoundFromSavedPicks($savedRound2, 2, $teamMap, $nameToIdMap, $pickOwnership, [])
            : $projected['round2'];

        return ['round1' => $round1, 'round2' => $round2];
    }

    /** @param list<int> $lotteryTeamIds */
    public function saveLotteryOrder(int $seasonYear, array $lotteryTeamIds): void
    {
        $projected = $this->calculateDraftOrder($seasonYear);
        $standings = $this->repository->getAllTeamsWithStandings();
        $teamMap = $this->buildTeamMap($standings);

        /** @var list<array{round: int, pick: int, team: string, teamid: int}> $picks */
        $picks = [];

        // Round 1: Picks 1-12 from the reordered lottery
        foreach ($lotteryTeamIds as $index => $teamid) {
            $team = $teamMap[$teamid] ?? null;
            if ($team === null) {
                throw new \InvalidArgumentException('Invalid team ID: ' . $teamid);
            }
            $picks[] = [
                'round' => 1,
                'pick' => $index + 1,
                'team' => $team['team_name'],
                'teamid' => $teamid,
            ];
        }

        // Round 1: Picks 13-28 from projected order
        foreach ($projected['round1'] as $slot) {
            if ($slot['pick'] >= 13) {
                $picks[] = [
                    'round' => 1,
                    'pick' => $slot['pick'],
                    'team' => $slot['teamName'],
                    'teamid' => $slot['teamId'],
                ];
            }
        }

        // Round 2: All 28 picks from projected order
        foreach ($projected['round2'] as $slot) {
            $picks[] = [
                'round' => 2,
                'pick' => $slot['pick'],
                'team' => $slot['teamName'],
                'teamid' => $slot['teamId'],
            ];
        }

        $this->repository->saveFinalDraftOrder($seasonYear, $picks);

        // Upsert the Draft Lottery Winner award for the team that owns pick #1
        $firstTeamName = $teamMap[$lotteryTeamIds[0]]['team_name'] ?? '';
        $pickOwnershipRows = $this->repository->getPickOwnership($seasonYear);
        $pickOwnership = $this->buildPickOwnershipMap($pickOwnershipRows);
        $ownership = $pickOwnership[$firstTeamName][1] ?? null;
        $ownerName = $ownership !== null ? $ownership['ownerName'] : $firstTeamName;
        $this->repository->upsertLotteryWinnerAward($seasonYear, $ownerName);
    }

    /**
     * Build draft slots from saved picks, enriching with standings and ownership data.
     *
     * @param list<array{pick: int, team: string, teamid: int, player: string}> $savedPicks
     * @param array<int, StandingsRow> $teamMap
     * @param array<string, int> $nameToIdMap
     * @param array<string, array<int, array{ownerName: string, notes: string}>> $pickOwnership
     * @param array<int, int> $projectedPickByTeam
     * @return list<DraftSlot>
     */
    private function buildRoundFromSavedPicks(array $savedPicks, int $round, array $teamMap, array $nameToIdMap, array $pickOwnership, array $projectedPickByTeam): array
    {
        $slots = [];
        foreach ($savedPicks as $savedPick) {
            $team = $teamMap[$savedPick['teamid']] ?? null;
            if ($team === null) {
                continue;
            }

            $teamName = $savedPick['team'];
            $ownership = $pickOwnership[$teamName][$round] ?? null;
            $ownerName = $ownership !== null ? $ownership['ownerName'] : $teamName;
            $notes = $ownership !== null ? $ownership['notes'] : '';
            $isTraded = $ownerName !== $teamName;
            $ownerId = $nameToIdMap[$ownerName] ?? $savedPick['teamid'];
            $ownerTeam = $teamMap[$ownerId] ?? $team;

            $projectedPick = $projectedPickByTeam[$savedPick['teamid']] ?? $savedPick['pick'];
            $movement = $projectedPick - $savedPick['pick'];

            $slots[] = [
                'pick' => $savedPick['pick'],
                'teamId' => $savedPick['teamid'],
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
                'movement' => $movement,
                'player' => $savedPick['player'],
            ];
        }

        return $slots;
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
     * @return array<int, float> teamid => net points (pointsFor - pointsAgainst)
     */
    private function buildPointDifferentialMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['teamid']] = $row['pointsFor'] - $row['pointsAgainst'];
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
            $map[$team['teamid']] = $team;
        }
        return $map;
    }

    /**
     * Determine playoff teams, division winners, conference winner, and non-playoff teams.
     *
     * @param list<StandingsRow> $conferenceTeams
     * @param array<int, array<int, int>> $h2h
     * @param array<int, float> $pointDiffs
     * @return array{wildCards: list<StandingsRow>, divisionWinners: list<StandingsRow>, conferenceWinner: StandingsRow|null, nonPlayoff: list<StandingsRow>}
     */
    private function determinePlayoffTeams(array $conferenceTeams, array $h2h, array $pointDiffs): array
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

        return $this->applyTiebreakers($a, $b, $h2h, $pointDiffs, 'better_wins');
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
    private function sortTeamsByRecord(array $teams, array $h2h, array $pointDiffs): array
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

        // Compute each team's aggregate H2H win pct against all other teams in the group
        /** @var array<int, float> */
        $aggregateH2HPct = [];
        foreach ($group as $team) {
            $totalWins = 0;
            $totalLosses = 0;
            foreach ($tids as $opponentTid) {
                if ($opponentTid === $team['teamid']) {
                    continue;
                }
                $totalWins += $h2h[$team['teamid']][$opponentTid] ?? 0;
                $totalLosses += $h2h[$opponentTid][$team['teamid']] ?? 0;
            }
            $aggregateH2HPct[$team['teamid']] = $this->safeWinPct($totalWins, $totalLosses);
        }

        // Sort ascending by aggregate H2H pct (worse H2H → earlier draft pick)
        // For sub-ties, fall through to non-H2H tiebreakers
        usort($group, function (array $a, array $b) use ($aggregateH2HPct, $pointDiffs): int {
            $h2hDiff = $aggregateH2HPct[$a['teamid']] <=> $aggregateH2HPct[$b['teamid']];
            if ($h2hDiff !== 0) {
                return $h2hDiff;
            }

            // Sub-tie: use non-H2H tiebreakers (negated for draft order)
            return -$this->applyNonH2HTiebreakers($a, $b, $pointDiffs);
        });

        return $group;
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
     * @param 'better_wins' $direction
     */
    private function applyTiebreakers(array $a, array $b, array $h2h, array $pointDiffs, string $direction): int
    {
        // 1. Head-to-head record (pairwise)
        $aWinsVsB = $h2h[$a['teamid']][$b['teamid']] ?? 0;
        $bWinsVsA = $h2h[$b['teamid']][$a['teamid']] ?? 0;
        if ($aWinsVsB !== $bWinsVsA) {
            return $bWinsVsA <=> $aWinsVsB;
        }

        // 2-6. Non-H2H tiebreakers
        return $this->applyNonH2HTiebreakers($a, $b, $pointDiffs);
    }

    /**
     * Apply non-H2H tiebreakers: division winner, division record, conference record,
     * point differential, alphabetical.
     *
     * Returns negative if A is the better team, positive if B is better.
     *
     * @param StandingsRow $a
     * @param StandingsRow $b
     * @param array<int, float> $pointDiffs
     */
    private function applyNonH2HTiebreakers(array $a, array $b, array $pointDiffs): int
    {
        // 2. Division winner status
        $aDivWinner = ($a['clinched_division'] ?? 0) === 1;
        $bDivWinner = ($b['clinched_division'] ?? 0) === 1;
        if ($aDivWinner !== $bDivWinner) {
            return $aDivWinner ? -1 : 1;
        }

        // 3. Division record (same-division teams only)
        if ($a['division'] === $b['division']) {
            $aDivPct = $this->safeWinPct($a['div_wins'], $a['div_losses']);
            $bDivPct = $this->safeWinPct($b['div_wins'], $b['div_losses']);
            $divDiff = $bDivPct <=> $aDivPct;
            if ($divDiff !== 0) {
                return $divDiff;
            }
        }

        // 4. Conference record
        $aConfPct = $this->safeWinPct($a['conf_wins'], $a['conf_losses']);
        $bConfPct = $this->safeWinPct($b['conf_wins'], $b['conf_losses']);
        $confDiff = $bConfPct <=> $aConfPct;
        if ($confDiff !== 0) {
            return $confDiff;
        }

        // 5. Point differential
        $aNetPts = $pointDiffs[$a['teamid']] ?? 0.0;
        $bNetPts = $pointDiffs[$b['teamid']] ?? 0.0;
        $ptsDiff = $bNetPts <=> $aNetPts;
        if ($ptsDiff !== 0) {
            return $ptsDiff;
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
        foreach ($teamMap as $teamid => $team) {
            $nameToIdMap[$team['team_name']] = $teamid;
        }

        foreach ($baseOrder as $index => $team) {
            $pickNumber = $index + 1;
            $teamName = $team['team_name'];

            $ownership = $pickOwnership[$teamName][$round] ?? null;
            $ownerName = $ownership !== null ? $ownership['ownerName'] : $teamName;
            $notes = $ownership !== null ? $ownership['notes'] : '';
            $isTraded = $ownerName !== $teamName;

            $ownerId = $nameToIdMap[$ownerName] ?? $team['teamid'];
            $ownerTeam = $teamMap[$ownerId] ?? $team;

            $slots[] = [
                'pick' => $pickNumber,
                'teamId' => $team['teamid'],
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
                'movement' => 0,
                'player' => '',
            ];
        }

        return $slots;
    }
}

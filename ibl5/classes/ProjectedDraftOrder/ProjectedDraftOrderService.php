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

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;

    private NonHeadToHeadTiebreaker $nonH2hTiebreaker;

    private DraftOrderTiebreakerResolver $draftOrderResolver;

    private PlayoffSeedingCalculator $playoffSeeding;

    public function __construct(ProjectedDraftOrderRepositoryInterface $repository, ?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->repository = $repository;
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->nonH2hTiebreaker = new NonHeadToHeadTiebreaker();
        $this->draftOrderResolver = new DraftOrderTiebreakerResolver($this->nonH2hTiebreaker);
        $this->playoffSeeding = new PlayoffSeedingCalculator($this->nonH2hTiebreaker);
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
            $result = $this->playoffSeeding->determinePlayoffTeams($conferenceTeams, $h2h, $pointDiffs);
            array_push($nonPlayoffTeams, ...$result['nonPlayoff']);
            array_push($wildCardTeams, ...$result['wildCards']);
            array_push($divisionWinnerTeams, ...$result['divisionWinners']);
            if ($result['conferenceWinner'] !== null) {
                $conferenceWinnerTeams[] = $result['conferenceWinner'];
            }
        }

        $teamMap = $this->buildTeamMap($standings);

        $nonPlayoffSorted = $this->draftOrderResolver->sortTeamsByRecord($nonPlayoffTeams, $h2h, $pointDiffs);
        $wildCardsSorted = $this->draftOrderResolver->sortTeamsByRecord($wildCardTeams, $h2h, $pointDiffs);
        $divisionWinnersSorted = $this->draftOrderResolver->sortTeamsByRecord($divisionWinnerTeams, $h2h, $pointDiffs);
        $conferenceWinnersSorted = $this->draftOrderResolver->sortTeamsByRecord($conferenceWinnerTeams, $h2h, $pointDiffs);

        $round1Order = array_merge($nonPlayoffSorted, $wildCardsSorted, $divisionWinnersSorted, $conferenceWinnersSorted);

        $allTeamsSorted = $this->draftOrderResolver->sortTeamsByRecord(array_values($teamMap), $h2h, $pointDiffs);

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

        $this->logger->info('lottery_order_saved', [
            'action' => 'lottery_order_saved',
            'season_year' => $seasonYear,
            'first_pick_team' => $firstTeamName,
        ]);
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
            $visitor = $game['visitor_teamid'];
            $home = $game['home_teamid'];
            if ($game['visitor_score'] > $game['home_score']) {
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

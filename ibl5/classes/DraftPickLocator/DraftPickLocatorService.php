<?php

declare(strict_types=1);

namespace DraftPickLocator;

use DraftPickLocator\Contracts\DraftPickLocatorRepositoryInterface;

/**
 * DraftPickLocatorService - Business logic for draft pick locator
 *
 * Processes draft pick data for display.
 *
 * @phpstan-import-type TeamWithPicks from Contracts\DraftPickLocatorViewInterface
 *
 * @see DraftPickLocatorRepositoryInterface For data access
 */
class DraftPickLocatorService
{
    private DraftPickLocatorRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param DraftPickLocatorRepositoryInterface $repository Data repository
     */
    public function __construct(DraftPickLocatorRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all teams with their draft picks
     *
     * @return list<array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}>
     */
    public function getAllTeamsWithPicks(): array
    {
        $teams = $this->repository->getAllTeams();
        $teamsWithPicks = [];

        foreach ($teams as $team) {
            $teamName = $team['team_name'];
            $picks = $this->repository->getDraftPicksForTeam($teamName);

            $teamsWithPicks[] = [
                'teamId' => $team['teamid'],
                'teamCity' => $team['team_city'],
                'teamName' => $teamName,
                'color1' => $team['color1'],
                'color2' => $team['color2'],
                'picks' => $picks,
            ];
        }

        return $teamsWithPicks;
    }
}

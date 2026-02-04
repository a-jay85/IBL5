<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimServiceInterface;
use Player\Player;
use Schedule\TeamSchedule;

/**
 * NextSimService - Business logic for next simulation games
 *
 * Processes upcoming game data and starting lineups.
 *
 * @phpstan-import-type NextSimGameData from \NextSim\Contracts\NextSimServiceInterface
 *
 * @see NextSimServiceInterface For the interface contract
 */
class NextSimService implements NextSimServiceInterface
{
    /** @phpstan-var \mysqli */
    private object $db;

    /**
     * Constructor
     *
     * @param object $db Database connection
     * @phpstan-param \mysqli $db
     */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see NextSimServiceInterface::getNextSimGames()
     *
     * @return array<int, NextSimGameData>
     */
    public function getNextSimGames(int $teamId, \Season $season): array
    {
        $projectedGames = TeamSchedule::getProjectedGamesNextSimResult(
            $this->db,
            $teamId,
            $season->lastSimEndDate
        );

        $lastSimEndDateObject = new \DateTime($season->lastSimEndDate);
        $games = [];

        foreach ($projectedGames as $gameRow) {
            /** @var array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int} $gameRow */
            $game = new \Game($gameRow);
            $gameDate = new \DateTime($game->date);
            $dayNumber = $gameDate->diff($lastSimEndDateObject)->format('%a');

            $opposingTeam = \Team::initialize($this->db, $game->getOpposingTeamID($teamId));

            $games[] = [
                'game' => $game,
                'date' => $gameDate,
                'dayNumber' => (int)$dayNumber,
                'opposingTeam' => $opposingTeam,
                'locationPrefix' => $game->getUserTeamLocationPrefix($teamId),
                'opposingStarters' => $this->getOpposingStartingLineup($opposingTeam),
            ];
        }

        return $games;
    }

    /**
     * @see NextSimServiceInterface::getUserStartingLineup()
     *
     * @return array<string, Player>
     */
    public function getUserStartingLineup(\Team $team): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $team->getCurrentlySetStarterPlayerIDForPosition($position);
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }

    /**
     * Get opposing team's starting lineup
     *
     * @param \Team $opposingTeam Opposing team
     * @return array<string, Player> Starting players by position
     */
    private function getOpposingStartingLineup(\Team $opposingTeam): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $opposingTeam->getLastSimStarterPlayerIDForPosition($position);
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }
}

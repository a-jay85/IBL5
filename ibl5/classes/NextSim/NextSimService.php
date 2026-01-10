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
 * @see NextSimServiceInterface For the interface contract
 */
class NextSimService implements NextSimServiceInterface
{
    private object $db;

    /**
     * Constructor
     *
     * @param object $db Database connection
     */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see NextSimServiceInterface::getNextSimGames()
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
     */
    public function getUserStartingLineup(\Team $team): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $team->getCurrentlySetStarterPlayerIDForPosition($position) ?? 4040404;
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }

    /**
     * Get opposing team's starting lineup
     *
     * @param \Team $opposingTeam Opposing team
     * @return array Starting players by position
     */
    private function getOpposingStartingLineup(\Team $opposingTeam): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $opposingTeam->getLastSimStarterPlayerIDForPosition($position) ?? 4040404;
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }
}

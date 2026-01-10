<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersServiceInterface;
use Player\Player;

/**
 * LeagueStartersService - Business logic for league starters display
 *
 * Retrieves starting lineups for all teams in the league.
 *
 * @see LeagueStartersServiceInterface For the interface contract
 */
class LeagueStartersService implements LeagueStartersServiceInterface
{
    private object $db;
    private \League $league;

    /**
     * Constructor
     *
     * @param object $db Database connection
     * @param \League $league League object
     */
    public function __construct(object $db, \League $league)
    {
        $this->db = $db;
        $this->league = $league;
    }

    /**
     * @see LeagueStartersServiceInterface::getAllStartersByPosition()
     */
    public function getAllStartersByPosition(): array
    {
        $teams = $this->league->getAllTeamsResult();
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $startersByPosition = [];

        foreach ($positions as $position) {
            $startersByPosition[$position] = [];
        }

        foreach ($teams as $teamRow) {
            $team = \Team::initialize($this->db, $teamRow);

            foreach ($positions as $position) {
                $playerId = $team->getLastSimStarterPlayerIDForPosition($position) ?: 4040404;
                $player = Player::withPlayerID($this->db, $playerId);
                $player->teamName = $team->name;
                $startersByPosition[$position][] = $player;
            }
        }

        return $startersByPosition;
    }
}

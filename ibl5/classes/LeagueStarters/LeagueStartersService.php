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
     *
     * @return array<string, array<int, Player>>
     */
    public function getAllStartersByPosition(): array
    {
        $teams = $this->league->getAllTeamsResult();
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        /** @var array<string, array<int, Player>> $startersByPosition */
        $startersByPosition = [];

        foreach ($positions as $position) {
            $startersByPosition[$position] = [];
        }

        foreach ($teams as $teamRow) {
            /** @var array<string, mixed> $teamRow */
            $team = \Team::initialize($this->db, $teamRow);

            foreach ($positions as $position) {
                $playerId = $team->getLastSimStarterPlayerIDForPosition($position);
                if ($playerId === 0) {
                    $playerId = 4040404;
                }
                $player = Player::withPlayerID($this->db, $playerId);
                $player->teamName = $team->name;
                $player->teamCity = $team->city;
                $player->teamColor1 = $team->color1;
                $player->teamColor2 = $team->color2;
                $startersByPosition[$position][] = $player;
            }
        }

        return $startersByPosition;
    }
}

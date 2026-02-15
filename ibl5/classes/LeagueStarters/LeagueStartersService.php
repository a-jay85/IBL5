<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersServiceInterface;
use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * LeagueStartersService - Business logic for league starters display
 *
 * Retrieves starting lineups for all teams in the league.
 *
 * @see LeagueStartersServiceInterface For the interface contract
 */
class LeagueStartersService implements LeagueStartersServiceInterface
{
    private \mysqli $db;
    private \League $league;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    /**
     * Constructor
     *
     * @param \mysqli $db Database connection
     * @param \League $league League object
     */
    public function __construct(\mysqli $db, \League $league)
    {
        $this->db = $db;
        $this->league = $league;
        $this->teamQueryRepo = new \Team\TeamQueryRepository($db);
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
                $playerId = $this->teamQueryRepo->getLastSimStarterPlayerIDForPosition($team->teamID, $position);
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

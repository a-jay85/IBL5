<?php

declare(strict_types=1);

namespace Injuries;

use Injuries\Contracts\InjuriesServiceInterface;
use Player\Player;
use Team;

/**
 * Service for retrieving injured players with team information.
 *
 * @see InjuriesServiceInterface
 */
class InjuriesService implements InjuriesServiceInterface
{
    private \League $league;
    private \mysqli $db;

    /**
     * @param \mysqli $db Database connection
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->league = new \League($db);
    }

    /**
     * @see InjuriesServiceInterface::getInjuredPlayersWithTeams()
     */
    public function getInjuredPlayersWithTeams(): array
    {
        $injuredPlayers = [];

        foreach ($this->league->getInjuredPlayersResult() as $injuredPlayerRow) {
            $player = Player::withPlrRow($this->db, $injuredPlayerRow);
            $team = Team::initialize($this->db, $player->teamID);

            $injuredPlayers[] = [
                'playerID' => $player->playerID,
                'name' => $player->name,
                'position' => $player->position,
                'daysRemaining' => $player->daysRemainingForInjury,
                'teamID' => $player->teamID,
                'teamCity' => $team->city,
                'teamName' => $team->name,
                'teamColor1' => $team->color1,
                'teamColor2' => $team->color2,
            ];
        }

        return $injuredPlayers;
    }
}

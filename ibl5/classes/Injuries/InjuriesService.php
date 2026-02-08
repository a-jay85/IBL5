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
        $season = new \Season($this->db);
        $injuredPlayers = [];

        /** @var array<int, array<string, mixed>> $injuredRows */
        $injuredRows = $this->league->getInjuredPlayersResult();

        foreach ($injuredRows as $injuredPlayerRow) {
            /** @phpstan-ignore argument.type (PlayerRow from SELECT * matches withPlrRow expectation) */
            $player = Player::withPlrRow($this->db, $injuredPlayerRow);
            $playerID = $player->playerID ?? 0;
            $team = Team::initialize($this->db, $playerID > 0 ? ($player->teamID ?? 0) : 0);

            $injuredPlayers[] = [
                'playerID' => $playerID,
                'name' => $player->name ?? '',
                'position' => $player->position ?? '',
                'daysRemaining' => $player->daysRemainingForInjury ?? 0,
                'returnDate' => $player->getInjuryReturnDate($season->lastSimEndDate),
                'teamID' => $player->teamID ?? 0,
                'teamCity' => $team->city,
                'teamName' => $team->name,
                'teamColor1' => $team->color1,
                'teamColor2' => $team->color2,
            ];
        }

        return $injuredPlayers;
    }
}

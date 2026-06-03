<?php

declare(strict_types=1);

namespace Injuries;

use Injuries\Contracts\InjuriesRepositoryInterface;
use Injuries\Contracts\InjuriesServiceInterface;
use Player\Player;
use Team\Team;
use Season\Season;

/**
 * Service for retrieving injured players with team information.
 *
 * @see InjuriesServiceInterface
 */
class InjuriesService implements InjuriesServiceInterface
{
    private \mysqli $db;
    private InjuriesRepositoryInterface $injuriesRepository;

    /**
     * @param \mysqli $db Database connection (still required for Player/Team/Season hydration)
     * @param InjuriesRepositoryInterface|null $injuriesRepository Source of injured-player rows; defaults to the League-backed repository
     */
    public function __construct(\mysqli $db, ?InjuriesRepositoryInterface $injuriesRepository = null)
    {
        $this->db = $db;
        $this->injuriesRepository = $injuriesRepository ?? new InjuriesRepository($db);
    }

    /**
     * @see InjuriesServiceInterface::getInjuredPlayersWithTeams()
     */
    public function getInjuredPlayersWithTeams(): array
    {
        $season = new Season($this->db);
        $injuredPlayers = [];

        $injuredRows = $this->injuriesRepository->getInjuredPlayers();

        foreach ($injuredRows as $injuredPlayerRow) {
            $player = Player::withPlrRow($this->db, $injuredPlayerRow);
            $playerID = $player->getPlayerID() ?? 0;
            $team = Team::initialize($this->db, $playerID > 0 ? ($player->getTeamid() ?? 0) : 0);

            $injuredPlayers[] = [
                'playerID' => $playerID,
                'name' => $player->getName() ?? '',
                'position' => $player->getPosition() ?? '',
                'daysRemaining' => $player->getDaysRemainingForInjury() ?? 0,
                'returnDate' => $player->getInjuryReturnDate($season->lastSimEndDate),
                'teamid' => $player->getTeamid() ?? 0,
                'teamCity' => $team->city,
                'teamName' => $team->name,
                'teamColor1' => $team->color1,
                'teamColor2' => $team->color2,
            ];
        }

        return $injuredPlayers;
    }
}

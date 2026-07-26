<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyEntityLoaderInterface;
use Player\Player;
use Team\Team;

final class FreeAgencyEntityLoader implements FreeAgencyEntityLoaderInterface
{
    public function __construct(private \mysqli $db)
    {
    }

    public function loadPlayer(int $playerID): Player
    {
        return Player::withPlayerID($this->db, $playerID);
    }

    public function loadTeam(string $teamName): Team
    {
        return Team::initialize($this->db, $teamName);
    }
}

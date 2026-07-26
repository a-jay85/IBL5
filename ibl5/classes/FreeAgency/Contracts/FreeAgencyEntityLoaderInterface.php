<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Player\Player;
use Team\Team;

interface FreeAgencyEntityLoaderInterface
{
    public function loadPlayer(int $playerID): Player;

    public function loadTeam(string $teamName): Team;
}

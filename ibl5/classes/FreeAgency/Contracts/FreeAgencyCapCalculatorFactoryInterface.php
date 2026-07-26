<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

use Team\Team;
use Season\Season;

interface FreeAgencyCapCalculatorFactoryInterface
{
    public function forTeam(Team $team, Season $season): FreeAgencyCapCalculatorInterface;
}

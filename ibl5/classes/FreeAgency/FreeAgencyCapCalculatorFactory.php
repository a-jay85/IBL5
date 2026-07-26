<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyCapCalculatorFactoryInterface;
use FreeAgency\Contracts\FreeAgencyCapCalculatorInterface;
use Team\Team;
use Season\Season;

final class FreeAgencyCapCalculatorFactory implements FreeAgencyCapCalculatorFactoryInterface
{
    public function __construct(private \mysqli $db)
    {
    }

    public function forTeam(Team $team, Season $season): FreeAgencyCapCalculatorInterface
    {
        return new FreeAgencyCapCalculator($this->db, $team, $season);
    }
}

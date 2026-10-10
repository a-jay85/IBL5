<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Schedule/index.php.
 */
final class ScheduleFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Schedule\ScheduleController
    {
        $db = $this->services->db();
        $leagueContext = $this->services->leagueContext();

        return new \Schedule\ScheduleController(
            $db,
            $leagueContext,
            $this->services->teamIdentity(),
            new \Standings\StandingsRepository($db, $leagueContext),
            new \TeamSchedule\TeamScheduleRepository($db, $leagueContext),
            new \LeagueSchedule\LeagueScheduleRepository($db, $leagueContext)
        );
    }
}

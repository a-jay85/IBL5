<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/NextSim/index.php.
 *
 * index.php resolves this factory only after its loginbox gate.
 */
final class NextSimFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function teamIdentity(): \Repositories\TeamIdentityRepository
    {
        return $this->services->teamIdentity();
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function standingsRepository(): \Standings\StandingsRepository
    {
        return new \Standings\StandingsRepository($this->services->db());
    }

    /**
     * @param array<int, float> $teamPowerRankings
     */
    public function service(array $teamPowerRankings): \NextSim\NextSimService
    {
        $db = $this->services->db();
        $teamScheduleRepository = new \TeamSchedule\TeamScheduleRepository($db);

        return new \NextSim\NextSimService($db, $teamScheduleRepository, $teamPowerRankings);
    }

    public function view(): \NextSim\NextSimView
    {
        return new \NextSim\NextSimView($this->services->season());
    }
}

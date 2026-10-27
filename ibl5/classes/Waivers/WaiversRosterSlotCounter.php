<?php

declare(strict_types=1);

namespace Waivers;

use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Waivers\Contracts\WaiversRosterSlotCounterInterface;

/**
 * @see WaiversRosterSlotCounterInterface
 */
final class WaiversRosterSlotCounter implements WaiversRosterSlotCounterInterface
{
    public function __construct(private readonly TeamQueryRepositoryInterface $teamQueryRepo)
    {
    }

    /**
     * @see WaiversRosterSlotCounterInterface::countOpenSlots()
     *
     * @return array{open: int, healthy: int}
     */
    public function countOpenSlots(int $teamID, Season $season): array
    {
        $allActive = $this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($teamID, $season);
        $healthyOnly = $this->teamQueryRepo->getHealthyPlayersOrderedByName($teamID, $season);

        return [
            'open' => Team::ROSTER_SPOTS_MAX - count($allActive),
            'healthy' => Team::ROSTER_SPOTS_MAX - count($healthyOnly),
        ];
    }
}

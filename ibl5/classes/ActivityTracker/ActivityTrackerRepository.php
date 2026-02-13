<?php

declare(strict_types=1);

namespace ActivityTracker;

use ActivityTracker\Contracts\ActivityTrackerRepositoryInterface;

/**
 * ActivityTrackerRepository - Database operations for activity tracker
 *
 * @phpstan-import-type ActivityRow from ActivityTrackerRepositoryInterface
 *
 * @see ActivityTrackerRepositoryInterface For the interface contract
 */
class ActivityTrackerRepository extends \BaseMysqliRepository implements ActivityTrackerRepositoryInterface
{
    /**
     * @see ActivityTrackerRepositoryInterface::getTeamActivity()
     *
     * @return list<ActivityRow>
     */
    public function getTeamActivity(): array
    {
        /** @var list<ActivityRow> */
        return $this->fetchAll(
            "SELECT teamid, team_name, team_city, color1, color2, depth, sim_depth, asg_vote, eoy_vote
             FROM ibl_team_info
             WHERE teamid != ?
             ORDER BY teamid ASC",
            'i',
            \League::FREE_AGENTS_TEAMID
        );
    }
}

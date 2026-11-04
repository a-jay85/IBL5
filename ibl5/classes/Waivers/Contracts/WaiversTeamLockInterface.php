<?php

declare(strict_types=1);

namespace Waivers\Contracts;

/**
 * Serializes waiver submissions for one team (backlog#1570).
 */
interface WaiversTeamLockInterface
{
    /**
     * Run $fn inside one transaction while holding a row lock on the team's
     * ibl_team_info row. A second caller for the same team waits until this
     * transaction commits or rolls back. Other teams are not blocked.
     *
     * Commits when $fn returns. Rolls back and rethrows when $fn throws.
     *
     * @template T
     * @param callable(): T $fn
     * @return T|null Null when no ibl_team_info row has this teamid; $fn is not called.
     */
    public function withTeamLock(int $teamId, callable $fn): mixed;
}

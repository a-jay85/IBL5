<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversTeamLockInterface;

/**
 * @see WaiversTeamLockInterface
 */
final class WaiversTeamLockRepository extends \Database\BaseMysqliRepository implements WaiversTeamLockInterface
{
    /**
     * @see WaiversTeamLockInterface::withTeamLock()
     */
    public function withTeamLock(int $teamId, callable $fn): mixed
    {
        return $this->transactional(function () use ($teamId, $fn): mixed {
            $row = $this->fetchOne(
                'SELECT teamid FROM `ibl_team_info` WHERE teamid = ? FOR UPDATE',
                'i',
                $teamId
            );
            if ($row === null) {
                return null;
            }

            return $fn();
        });
    }
}

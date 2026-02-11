<?php

declare(strict_types=1);

namespace CapSpace;

use CapSpace\Contracts\CapSpaceRepositoryInterface;

/**
 * CapSpaceRepository - Data access layer for salary cap information
 *
 * Retrieves team salary and roster data for cap display.
 *
 * @see CapSpaceRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class CapSpaceRepository extends \BaseMysqliRepository implements CapSpaceRepositoryInterface
{
    /**
     * @see CapSpaceRepositoryInterface::getAllTeams()
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTeams(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_team_info WHERE teamid BETWEEN 1 AND ? ORDER BY teamid ASC",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see CapSpaceRepositoryInterface::getPlayersUnderContractAfterSeason()
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlayersUnderContractAfterSeason(int $teamId): array
    {
        return $this->fetchAll(
            "SELECT cy, cyt FROM ibl_plr
             WHERE retired = 0
               AND tid = ?
               AND cy <> cyt
               AND name NOT LIKE '%|%'",
            "i",
            $teamId
        );
    }
}

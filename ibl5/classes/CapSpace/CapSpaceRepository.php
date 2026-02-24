<?php

declare(strict_types=1);

namespace CapSpace;

use CapSpace\Contracts\CapSpaceRepositoryInterface;

/**
 * CapSpaceRepository - Data access layer for salary cap information
 *
 * Retrieves team salary and roster data for cap display.
 *
 * @phpstan-import-type TeamInfoRow from CapSpaceRepositoryInterface
 * @phpstan-import-type ContractRow from CapSpaceRepositoryInterface
 *
 * @see CapSpaceRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class CapSpaceRepository extends \BaseMysqliRepository implements CapSpaceRepositoryInterface
{
    /**
     * @see CapSpaceRepositoryInterface::getAllTeams()
     *
     * @return list<TeamInfoRow>
     */
    public function getAllTeams(): array
    {
        /** @var list<TeamInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_team_info WHERE teamid BETWEEN 1 AND ? ORDER BY teamid ASC",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see CapSpaceRepositoryInterface::getPlayersUnderContractAfterSeason()
     *
     * @return list<ContractRow>
     */
    public function getPlayersUnderContractAfterSeason(int $teamId): array
    {
        /** @var list<ContractRow> */
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

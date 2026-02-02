<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListRepositoryInterface;

/**
 * ContractListRepository - Data access layer for player contracts
 *
 * Retrieves player contract information from the ibl_plr table.
 *
 * @see ContractListRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class ContractListRepository extends \BaseMysqliRepository implements ContractListRepositoryInterface
{
    /**
     * @see ContractListRepositoryInterface::getActivePlayerContracts()
     */
    public function getActivePlayerContracts(): array
    {
        $query = "SELECT p.pid, p.name, p.pos, p.teamname, p.tid, p.cy, p.cyt, p.cy1, p.cy2, p.cy3, p.cy4, p.cy5, p.cy6, p.bird,
                         t.team_city, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.retired = 0
            ORDER BY p.ordinal ASC";

        return $this->fetchAll($query);
    }
}

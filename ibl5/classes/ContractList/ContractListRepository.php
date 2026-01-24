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
        $query = "SELECT name, pos, teamname, cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6, bird
            FROM ibl_plr
            WHERE retired = 0
            ORDER BY ordinal ASC";

        return $this->fetchAll($query);
    }
}

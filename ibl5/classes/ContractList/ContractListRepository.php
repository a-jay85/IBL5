<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListRepositoryInterface;

/**
 * ContractListRepository - Data access layer for player contracts
 *
 * Retrieves player contract information from the ibl_plr table.
 *
 * @phpstan-type ContractPlayerRow array{pid: int, name: string, pos: string, teamname: string, tid: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, bird: string, team_city: string|null, color1: string|null, color2: string|null}
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

        /** @var list<array{name: string, pos: string, teamname: string, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, bird: string, pid: int, tid: int, team_city: string|null, color1: string|null, color2: string|null}> */
        return $this->fetchAll($query);
    }
}

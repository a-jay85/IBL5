<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListRepositoryInterface;

/**
 * ContractListRepository - Data access layer for player contracts
 *
 * Retrieves player contract information from the ibl_plr table.
 *
 * @phpstan-type ContractPlayerRow array{pid: int, name: string, pos: string, teamname: string, teamid: int, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, bird: string, team_city: string|null, color1: string|null, color2: string|null}
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
        $query = "SELECT p.pid, p.name, p.pos, t.team_name AS teamname, p.teamid, p.cy, p.cyt, p.salary_yr1, p.salary_yr2, p.salary_yr3, p.salary_yr4, p.salary_yr5, p.salary_yr6, p.bird,
                         t.team_city, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.retired = 0
            ORDER BY p.ordinal ASC";

        /** @var list<array{name: string, pos: string, teamname: string, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, bird: string, pid: int, teamid: int, team_city: string|null, color1: string|null, color2: string|null}> */
        return $this->fetchAll($query);
    }
}

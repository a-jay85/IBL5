<?php

declare(strict_types=1);

namespace AllStarAppearances;

use AllStarAppearances\Contracts\AllStarAppearancesRepositoryInterface;

/**
 * AllStarAppearancesRepository - Data access layer for all-star appearances
 *
 * Retrieves all-star appearance counts from the ibl_awards table.
 *
 * @see AllStarAppearancesRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class AllStarAppearancesRepository extends \BaseMysqliRepository implements AllStarAppearancesRepositoryInterface
{
    /**
     * @see AllStarAppearancesRepositoryInterface::getAllStarAppearances()
     */
    public function getAllStarAppearances(): array
    {
        $query = "SELECT a.name, h.pid, COUNT(*) as appearances
            FROM ibl_awards a
            LEFT JOIN (SELECT DISTINCT pid, name FROM ibl_hist) h ON h.name = a.name
            WHERE a.Award LIKE '%Conference All-Star'
            GROUP BY a.name, h.pid
            ORDER BY appearances DESC, a.name ASC";

        return $this->fetchAll($query);
    }
}

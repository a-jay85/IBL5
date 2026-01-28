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
        $query = "SELECT a.name, a.pid, COUNT(*) as appearances
            FROM ibl_awards a
            WHERE a.Award LIKE '%Conference All-Star'
            GROUP BY a.name, a.pid
            ORDER BY appearances DESC, a.name ASC";

        return $this->fetchAll($query);
    }
}

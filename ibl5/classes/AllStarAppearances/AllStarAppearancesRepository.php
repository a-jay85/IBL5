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
        $query = "SELECT name, COUNT(*) as appearances
            FROM ibl_awards
            WHERE Award LIKE '%Conference All-Star'
            GROUP BY name
            ORDER BY appearances DESC, name ASC";

        return $this->fetchAll($query);
    }
}

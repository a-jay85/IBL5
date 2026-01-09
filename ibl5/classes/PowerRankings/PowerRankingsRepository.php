<?php

declare(strict_types=1);

namespace PowerRankings;

use PowerRankings\Contracts\PowerRankingsRepositoryInterface;

/**
 * PowerRankingsRepository - Data access layer for power rankings
 *
 * Retrieves power rankings data from ibl_power table.
 *
 * @see PowerRankingsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class PowerRankingsRepository extends \BaseMysqliRepository implements PowerRankingsRepositoryInterface
{
    /**
     * @see PowerRankingsRepositoryInterface::getPowerRankings()
     */
    public function getPowerRankings(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY ranking DESC"
        );
    }
}

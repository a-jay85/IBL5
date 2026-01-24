<?php

declare(strict_types=1);

namespace FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewRepositoryInterface;

/**
 * FreeAgencyPreviewRepository - Data access layer for free agency preview
 *
 * Retrieves player contract and rating information from the ibl_plr table.
 *
 * @see FreeAgencyPreviewRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class FreeAgencyPreviewRepository extends \BaseMysqliRepository implements FreeAgencyPreviewRepositoryInterface
{
    /**
     * @see FreeAgencyPreviewRepositoryInterface::getActivePlayers()
     */
    public function getActivePlayers(): array
    {
        $query = "SELECT pid, tid, name, teamname, pos, age, draftyear, exp, cy, cyt,
                         r_fga, r_fgp, r_fta, r_ftp, r_tga, r_tgp,
                         r_orb, r_drb, r_ast, r_stl, r_blk, r_to, r_foul,
                         oo, `do`, po, `to`, od, dd, pd, td,
                         loyalty, winner, playingTime, security, tradition
            FROM ibl_plr
            WHERE retired = 0
            ORDER BY ordinal ASC";

        return $this->fetchAll($query);
    }
}

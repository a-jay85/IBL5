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
 *
 * @phpstan-import-type ActivePlayerRow from FreeAgencyPreviewRepositoryInterface
 */
class FreeAgencyPreviewRepository extends \BaseMysqliRepository implements FreeAgencyPreviewRepositoryInterface
{
    /**
     * @see FreeAgencyPreviewRepositoryInterface::getActivePlayers()
     *
     * @return list<ActivePlayerRow>
     */
    public function getActivePlayers(): array
    {
        $query = "SELECT p.pid, p.teamid, p.name, t.team_name AS teamname, p.pos, p.age, p.draftyear, p.exp, p.cy, p.cyt,
                         p.cy1, p.cy2, p.cy3, p.cy4, p.cy5, p.cy6,
                         p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_3ga, p.r_3gp,
                         p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_blk, p.r_tvr, p.r_foul,
                         p.oo, p.r_drive_off, p.po, p.r_trans_off, p.od, p.dd, p.pd, p.td,
                         p.loyalty, p.winner, p.playingTime, p.security, p.tradition,
                         t.team_city, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.retired = 0
            ORDER BY p.ordinal ASC";

        /** @var list<ActivePlayerRow> */
        return $this->fetchAll($query);
    }
}

<?php

declare(strict_types=1);

namespace RatingsDiff;

use BaseMysqliRepository;
use RatingsDiff\Contracts\RatingsDiffRepositoryInterface;

/**
 * RatingsDiffRepository — fetches ibl_plr and ibl_plr_snapshots data for the diff page.
 *
 * Column name notes (migration 113):
 *   - `to` was renamed to `r_trans_off` (transition offense rating)
 *   - `do` was renamed to `r_drive_off` (drive offense rating)
 *   - `r_to` was renamed to `r_tvr` (turnover rating)
 */
class RatingsDiffRepository extends BaseMysqliRepository implements RatingsDiffRepositoryInterface
{
    /**
     * @see RatingsDiffRepositoryInterface::getLatestEndOfSeasonYear()
     */
    public function getLatestEndOfSeasonYear(): ?int
    {
        $row = $this->fetchOne(
            "SELECT MAX(season_year) AS y FROM ibl_plr_snapshots WHERE snapshot_phase = 'end-of-season'",
            '',
        );

        if ($row === null) {
            return null;
        }

        $y = $row['y'] ?? null;
        if ($y === null) {
            return null;
        }
        if (is_int($y)) {
            return $y;
        }
        if (is_numeric($y)) {
            return (int) $y;
        }
        return null;
    }

    /**
     * @see RatingsDiffRepositoryInterface::getDiffRows()
     *
     * @return list<array<string, mixed>>
     */
    public function getDiffRows(int $baselineYear, ?int $filterTid = null): array
    {
        $sql = <<<'SQL'
SELECT
    p.pid, p.name, p.pos, p.tid,
    t.team_name,
    p.oo, p.od, p.r_drive_off, p.dd, p.po, p.pd, p.r_trans_off, p.td,
    p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_tga, p.r_tgp,
    p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_tvr, p.r_blk, p.r_foul,
    s.oo      AS s_oo,      s.od      AS s_od,      s.r_drive_off AS s_r_drive_off,
    s.dd      AS s_dd,      s.po      AS s_po,      s.pd          AS s_pd,
    s.r_trans_off AS s_r_trans_off,                 s.td          AS s_td,
    s.r_fga   AS s_r_fga,   s.r_fgp   AS s_r_fgp,
    s.r_fta   AS s_r_fta,   s.r_ftp   AS s_r_ftp,
    s.r_tga   AS s_r_tga,   s.r_tgp   AS s_r_tgp,
    s.r_orb   AS s_r_orb,   s.r_drb   AS s_r_drb,
    s.r_ast   AS s_r_ast,   s.r_stl   AS s_r_stl,
    s.r_tvr   AS s_r_tvr,   s.r_blk   AS s_r_blk,  s.r_foul AS s_r_foul
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON t.teamid = p.tid
LEFT JOIN ibl_plr_snapshots s
       ON s.pid = p.pid
      AND s.season_year = ?
      AND s.snapshot_phase = 'end-of-season'
WHERE p.retired = 0
SQL;

        if ($filterTid !== null) {
            $sql .= ' AND p.tid = ?';
            $sql .= ' ORDER BY p.name';
            return array_values($this->fetchAll($sql, 'ii', $baselineYear, $filterTid));
        }

        $sql .= ' ORDER BY p.name';
        return array_values($this->fetchAll($sql, 'i', $baselineYear));
    }
}

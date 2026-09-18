<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff;

use BaseMysqliRepository;
use TrainingCampRatingsDiff\Contracts\TrainingCampRatingsDiffRepositoryInterface;

/**
 * TrainingCampRatingsDiffRepository — fetches ibl_plr and ibl_plr_snapshots data for the diff page.
 *
 * Column name notes (migration 113):
 *   - `to` was renamed to `r_trans_off` (transition offense rating)
 *   - `do` was renamed to `r_drive_off` (drive offense rating)
 *   - `r_to` was renamed to `r_tvr` (turnover rating)
 */
class TrainingCampRatingsDiffRepository extends BaseMysqliRepository implements TrainingCampRatingsDiffRepositoryInterface
{
    /**
     * @see TrainingCampRatingsDiffRepositoryInterface::getBaselinePhase()
     *
     * The FIELD() list is the preference order: the latest playoffs snapshot first,
     * then the end-of-season and mid-season fallbacks. 'end-of-season' ranks below
     * the playoffs phases because it can hold offseason/preseason ratings. The
     * archive-named phases come from bulk imports; 'playoffs' comes from the live
     * updater (SnapshotPlrStep) or a bulk import of {season}_{NN}_playoffs archives.
     */
    public function getBaselinePhase(int $seasonYear): ?string
    {
        $row = $this->fetchOne(
            "SELECT snapshot_phase,
                    FIELD(snapshot_phase,
                          'finals', 'playoffs',
                          'conf-finals-gm4-7', 'conf-finals-gm1-3',
                          'playoffs-rd2-gm4-7', 'playoffs-rd2-gm1-3',
                          'playoffs-rd1-gm4-7', 'playoffs-rd1-gm1-3',
                          'end-of-season', 'mid-season') AS phase_rank
               FROM `ibl_plr_snapshots`
              WHERE season_year = ?
             HAVING phase_rank > 0
              ORDER BY phase_rank
              LIMIT 1",
            'i',
            $seasonYear,
        );

        if ($row === null) {
            return null;
        }

        $v = $row['snapshot_phase'] ?? null;
        return is_string($v) ? $v : null;
    }

    /**
     * @see TrainingCampRatingsDiffRepositoryInterface::getDiffRows()
     *
     * @return list<array<string, mixed>>
     */
    public function getDiffRows(int $baselineYear, string $baselinePhase, ?int $filterTid = null, string $filterStatus = ''): array
    {
        $sql = <<<'SQL'
SELECT
    p.pid, p.name, p.pos, p.age, p.teamid,
    t.team_name, t.color1, t.color2,
    p.oo, p.od, p.r_drive_off, p.dd, p.po, p.pd, p.r_trans_off, p.td,
    p.r_fga, p.r_fgp, p.r_fta, p.r_ftp, p.r_3ga, p.r_3gp,
    p.r_orb, p.r_drb, p.r_ast, p.r_stl, p.r_tvr, p.r_blk, p.r_foul,
    s.oo      AS s_oo,      s.od      AS s_od,      s.r_drive_off AS s_r_drive_off,
    s.dd      AS s_dd,      s.po      AS s_po,      s.pd          AS s_pd,
    s.r_trans_off AS s_r_trans_off,                 s.td          AS s_td,
    s.r_fga   AS s_r_fga,   s.r_fgp   AS s_r_fgp,
    s.r_fta   AS s_r_fta,   s.r_ftp   AS s_r_ftp,
    s.r_3ga   AS s_r_3ga,   s.r_3gp   AS s_r_3gp,
    s.r_orb   AS s_r_orb,   s.r_drb   AS s_r_drb,
    s.r_ast   AS s_r_ast,   s.r_stl   AS s_r_stl,
    s.r_tvr   AS s_r_tvr,   s.r_blk   AS s_r_blk,  s.r_foul AS s_r_foul
FROM `ibl_plr` p
LEFT JOIN `ibl_team_info` t ON t.teamid = p.teamid
LEFT JOIN `ibl_plr_snapshots` s
       ON s.pid = p.pid
      AND s.season_year = ?
      AND s.snapshot_phase = ?
WHERE p.retired = 0
SQL;

        if ($filterStatus === 'signed') {
            $sql .= ' AND p.teamid > 0';
        } elseif ($filterStatus === 'fa') {
            $sql .= ' AND p.teamid = 0';
        }

        if ($filterTid !== null) {
            $sql .= ' AND p.teamid = ?';
            $sql .= ' ORDER BY p.name';
            return array_values($this->fetchAll($sql, 'isi', $baselineYear, $baselinePhase, $filterTid));
        }

        $sql .= ' ORDER BY p.name';
        return array_values($this->fetchAll($sql, 'is', $baselineYear, $baselinePhase));
    }
}

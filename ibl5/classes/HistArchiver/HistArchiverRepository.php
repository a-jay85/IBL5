<?php

declare(strict_types=1);

namespace HistArchiver;

use BaseMysqliRepository;
use HistArchiver\Contracts\HistArchiverRepositoryInterface;

/**
 * @see HistArchiverRepositoryInterface
 */
class HistArchiverRepository extends BaseMysqliRepository implements HistArchiverRepositoryInterface
{
    /** ibl_plr → ibl_hist rating column mapping */
    private const RATING_COLUMN_MAP = [
        'r_fga' => 'r_2ga',
        'r_fgp' => 'r_2gp',
        'r_fta' => 'r_fta',
        'r_ftp' => 'r_ftp',
        'r_tga' => 'r_3ga',
        'r_tgp' => 'r_3gp',
        'r_orb' => 'r_orb',
        'r_drb' => 'r_drb',
        'r_ast' => 'r_ast',
        'r_stl' => 'r_stl',
        'r_blk' => 'r_blk',
        'r_to'  => 'r_tvr',
        'oo'    => 'r_oo',
        'od'    => 'r_od',
        '`do`'  => 'r_do',
        'dd'    => 'r_dd',
        'po'    => 'r_po',
        'pd'    => 'r_pd',
        '`to`'  => 'r_to',
        'td'    => 'r_td',
    ];

    /**
     * @see HistArchiverRepositoryInterface::hasChampionForYear()
     */
    public function hasChampionForYear(int $year): bool
    {
        $row = $this->fetchOne(
            "SELECT 1 AS found FROM vw_team_awards WHERE Award = 'IBL Champions' AND year = ? LIMIT 1",
            'i',
            $year,
        );

        return $row !== null;
    }

    /**
     * @see HistArchiverRepositoryInterface::getRegularSeasonTotals()
     *
     * @return list<array<string, mixed>>
     */
    public function getRegularSeasonTotals(int $year): array
    {
        /** @var list<array<string, mixed>> */
        return $this->fetchAll(
            'SELECT * FROM ibl_regular_season_stats WHERE year = ?',
            'i',
            $year,
        );
    }

    /**
     * @see HistArchiverRepositoryInterface::getPlayerRatingsAndContract()
     */
    public function getPlayerRatingsAndContract(int $pid): ?array
    {
        $row = $this->fetchOne(
            'SELECT tid, cy, cy1, cy2, cy3, cy4, cy5,
                    r_fga, r_fgp, r_fta, r_ftp, r_tga, r_tgp,
                    r_orb, r_drb, r_ast, r_stl, r_to, r_blk,
                    oo, od, `do`, dd, po, pd, `to`, td
             FROM ibl_plr WHERE pid = ?',
            'i',
            $pid,
        );

        if ($row === null) {
            return null;
        }

        $intVal = static fn (string $key) => is_int($row[$key]) ? $row[$key] : (int) (is_string($row[$key]) ? $row[$key] : 0);

        $cy = $intVal('cy');
        $salary = ($cy >= 1 && $cy <= 5) ? $intVal('cy' . $cy) : 0;

        $mapped = ['tid' => $intVal('tid'), 'salary' => $salary];
        foreach (self::RATING_COLUMN_MAP as $plrCol => $histCol) {
            $lookupKey = str_replace('`', '', $plrCol);
            $mapped[$histCol] = $intVal($lookupKey);
        }

        /** @var array{tid: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_od: int, r_do: int, r_dd: int, r_po: int, r_pd: int, r_to: int, r_td: int, salary: int} $mapped */
        return $mapped;
    }

    /**
     * @see HistArchiverRepositoryInterface::upsertHistRow()
     */
    public function upsertHistRow(array $data): int
    {
        $query = "INSERT INTO ibl_hist
            (`pid`, `name`, `year`, `team`, `teamid`,
             `games`, `minutes`, `fgm`, `fga`, `ftm`, `fta`, `tgm`, `tga`,
             `orb`, `reb`, `ast`, `stl`, `blk`, `tvr`, `pf`, `pts`,
             `r_2ga`, `r_2gp`, `r_fta`, `r_ftp`, `r_3ga`, `r_3gp`,
             `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_blk`, `r_tvr`,
             `r_oo`, `r_od`, `r_do`, `r_dd`, `r_po`, `r_pd`, `r_to`, `r_td`,
             `salary`)
        VALUES
            (?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?)
        ON DUPLICATE KEY UPDATE
            `team` = VALUES(`team`),
            `teamid` = VALUES(`teamid`),
            `games` = VALUES(`games`),
            `minutes` = VALUES(`minutes`),
            `fgm` = VALUES(`fgm`),
            `fga` = VALUES(`fga`),
            `ftm` = VALUES(`ftm`),
            `fta` = VALUES(`fta`),
            `tgm` = VALUES(`tgm`),
            `tga` = VALUES(`tga`),
            `orb` = VALUES(`orb`),
            `reb` = VALUES(`reb`),
            `ast` = VALUES(`ast`),
            `stl` = VALUES(`stl`),
            `blk` = VALUES(`blk`),
            `tvr` = VALUES(`tvr`),
            `pf` = VALUES(`pf`),
            `pts` = VALUES(`pts`),
            `r_2ga` = VALUES(`r_2ga`),
            `r_2gp` = VALUES(`r_2gp`),
            `r_fta` = VALUES(`r_fta`),
            `r_ftp` = VALUES(`r_ftp`),
            `r_3ga` = VALUES(`r_3ga`),
            `r_3gp` = VALUES(`r_3gp`),
            `r_orb` = VALUES(`r_orb`),
            `r_drb` = VALUES(`r_drb`),
            `r_ast` = VALUES(`r_ast`),
            `r_stl` = VALUES(`r_stl`),
            `r_blk` = VALUES(`r_blk`),
            `r_tvr` = VALUES(`r_tvr`),
            `r_oo` = VALUES(`r_oo`),
            `r_od` = VALUES(`r_od`),
            `r_do` = VALUES(`r_do`),
            `r_dd` = VALUES(`r_dd`),
            `r_po` = VALUES(`r_po`),
            `r_pd` = VALUES(`r_pd`),
            `r_to` = VALUES(`r_to`),
            `r_td` = VALUES(`r_td`),
            `salary` = VALUES(`salary`)";

        // 42 params: pid(i) name(s) year(i) team(s) teamid(i) + 37 ints
        $types = 'isisi'     // pid, name, year, team, teamid
            . 'iiiiiiii'     // games, minutes, fgm, fga, ftm, fta, tgm, tga (8)
            . 'iiiiiiii'     // orb, reb, ast, stl, blk, tvr, pf, pts (8)
            . 'iiiiii'       // r_2ga..r_3gp
            . 'iiiiii'       // r_orb..r_tvr
            . 'iiiiiiii'     // r_oo..r_td
            . 'i';           // salary

        return $this->execute(
            $query,
            $types,
            (int) $data['pid'],
            (string) $data['name'],
            (int) $data['year'],
            (string) $data['team'],
            (int) $data['teamid'],
            (int) $data['games'],
            (int) $data['minutes'],
            (int) $data['fgm'],
            (int) $data['fga'],
            (int) $data['ftm'],
            (int) $data['fta'],
            (int) $data['tgm'],
            (int) $data['tga'],
            (int) $data['orb'],
            (int) $data['reb'],
            (int) $data['ast'],
            (int) $data['stl'],
            (int) $data['blk'],
            (int) $data['tvr'],
            (int) $data['pf'],
            (int) $data['pts'],
            (int) $data['r_2ga'],
            (int) $data['r_2gp'],
            (int) $data['r_fta'],
            (int) $data['r_ftp'],
            (int) $data['r_3ga'],
            (int) $data['r_3gp'],
            (int) $data['r_orb'],
            (int) $data['r_drb'],
            (int) $data['r_ast'],
            (int) $data['r_stl'],
            (int) $data['r_blk'],
            (int) $data['r_tvr'],
            (int) $data['r_oo'],
            (int) $data['r_od'],
            (int) $data['r_do'],
            (int) $data['r_dd'],
            (int) $data['r_po'],
            (int) $data['r_pd'],
            (int) $data['r_to'],
            (int) $data['r_td'],
            (int) $data['salary'],
        );
    }

    /**
     * @see HistArchiverRepositoryInterface::getValidationComparison()
     *
     * @return list<array<string, mixed>>
     */
    public function getValidationComparison(int $year): array
    {
        /** @var list<array<string, mixed>> */
        return $this->fetchAll(
            "SELECT
                h.pid, h.name,
                h.games AS hist_games, rs.games AS bs_games,
                h.minutes AS hist_minutes, rs.minutes AS bs_minutes,
                h.fgm AS hist_fgm, rs.fgm AS bs_fgm,
                h.fga AS hist_fga, rs.fga AS bs_fga,
                h.ftm AS hist_ftm, rs.ftm AS bs_ftm,
                h.fta AS hist_fta, rs.fta AS bs_fta,
                h.tgm AS hist_tgm, rs.tgm AS bs_tgm,
                h.tga AS hist_tga, rs.tga AS bs_tga,
                h.orb AS hist_orb, rs.orb AS bs_orb,
                h.reb AS hist_reb, rs.reb AS bs_reb,
                h.ast AS hist_ast, rs.ast AS bs_ast,
                h.stl AS hist_stl, rs.stl AS bs_stl,
                h.tvr AS hist_tvr, rs.tvr AS bs_tvr,
                h.blk AS hist_blk, rs.blk AS bs_blk,
                h.pf AS hist_pf, rs.pf AS bs_pf,
                h.pts AS hist_pts, rs.pts AS bs_pts
            FROM ibl_hist h
            JOIN ibl_regular_season_stats rs ON h.pid = rs.pid AND h.year = rs.year
            WHERE h.year = ?",
            'i',
            $year,
        );
    }

}
